<?php

namespace vokkin\tic_tac_toe\Controller;

    use vokkin\tic_tac_toe\Model\Board as Board;
    use Exception as Exception;
    use LogicException as LogicException;
    use RedBeanPHP\R as R;

    use function cli\prompt;
    use function cli\line;
    use function cli\out;

    use function vokkin\tic_tac_toe\View\showGameBoard;
    use function vokkin\tic_tac_toe\View\showMessage;
    use function vokkin\tic_tac_toe\View\getValue;

    use const vokkin\tic_tac_toe\Model\PLAYER_X_MARKUP;
    use const vokkin\tic_tac_toe\Model\PLAYER_O_MARKUP;

function startGame()
{
    if (file_exists("gamedb.db")) {
        R::setup("sqlite:gamedb.db");
    }
    while (true) {
        $command = prompt("Введите команду\n'--new' - начать новую игру;\n'--list' - показать результаты игр;\n'--replay [#]' - показать партию (#-номер партии);\n'--exit' - выход;");
        $gameBoard = new Board();
        if ($command == "--new") {
            play($gameBoard);
        } elseif ($command == "--list") {
            listGames($gameBoard);
        } elseif (preg_match('/(^--replay [0-9]+$)/', $command) != 0) {
            $id = explode(' ', $command)[1];
            replayGame($gameBoard, $id);
        } elseif ($command == "--exit") {
            exit("Thanks for using\n");
        } else {
            line("Key not found");
        }
    }
}

function play($gameBoard) 
{
    $canContinue = true;
    do {
        initialize($gameBoard);
        gameLoop($gameBoard);
        inviteToContinue($canContinue);
    } while ($canContinue);
}

function initialize($board)
{
    try {
        $board->setUserName(getValue("Введите имя игрока "));
        $board->setDimension(getValue("Введите размер игрового поля "));
        $board->initialize();
    } catch (Exception $e) {
        showMessage($e->getMessage());
        initialize($board);
    }
}

function gameLoop($board)
{
    $stopGame = false;
    $currentMarkup = PLAYER_X_MARKUP;
    $endGameMsg = "";
    $db = $board->OpenDatabase();

    date_default_timezone_set("Europe/Moscow");
    $gameData = date("d") . "." . date("m") . "." . date("Y");
    $gameTime = date("H") . ":" . date("i") . ":" . date("s");
    $playerName =  $board->getUser();
    $size = $board->getDimension();

    R::exec("INSERT INTO gamesInfo (
        gameData, 
        gameTime, 
        playerName, 
        sizeBoard, 
        result
        ) VALUES (
        '$gameData', 
        '$gameTime', 
        '$playerName', 
        '$size', 
        'НЕ ЗАКОНЧЕНО')");

    $id = R::querySingle("SELECT idGame FROM gamesInfo ORDER BY idGame DESC LIMIT 1");

    $board->setId($id);
    $gameId = $board->getGameId();

    do {
        showGameBoard($board);
        if ($currentMarkup == $board->getUserMarkup()) {
            processUserTurn($board, $currentMarkup, $stopGame);
            $endGameMsg = "Игрок '$currentMarkup' победил.";
            $currentMarkup = $board->getComputerMarkup();
        } else {
            processComputerTurn($board, $currentMarkup, $stopGame);
            $endGameMsg = "Игрок '$currentMarkup' победил.";
            $currentMarkup = $board->getUserMarkup();
        }

        if (!$board->isFreeSpaceEnough() && !$stopGame) {
            showGameBoard($board);
            $endGameMsg = "Ничья";
            $stopGame = true;
        }
    } while (!$stopGame);

    $temp_mark = $board->getUserMarkup();
    if ($endGameMsg == "Игрок '$temp_mark' победил в партии."){
        $result = 'ПОБЕДА';
        $board->endGame($gameId, $result, $db);
    }
    else{
        $result = 'ПОРАЖЕНИЕ';
        $board->endGame($gameId, $result, $db);
    }

    showGameBoard($board);
    showMessage($endGameMsg);
}

function processUserTurn($board, $markup, &$stopGame, $db)
{
    $answerTaked = false;
    do {
        try {
            $coords = getCoords($board);
            $board->setMarkupOnBoard($coords[0], $coords[1], $markup);
            $idGame = $board->getGameId();
            $mark = $board->getMarkup();
            $col = $coords[0] + 1;
            $row = $coords[1] + 1;
            R::exec("INSERT INTO stepsInfo (
                idGame, 
                playerMark, 
                rowCoord, 
                colCoord
                ) VALUES (
                '$idGame', 
                '$mark', 
                '$col', 
                '$row')");
            if ($board->determineWinner($coords[0], $coords[1]) !== "") {
                $stopGame = true;
            }

            $answerTaked = true;
        } catch (Exception $e) {
            showMessage($e->getMessage());
        }
    } while (!$answerTaked);
    return $db;
}

function getCoords($board)
{
    $markup = $board->getUserMarkup();
    $name = $board->getUser();
    $coords = getValue("Введите координаты игрока '$markup' (номер строки и номер столбца через ; ) ");
    if ($coords == "--exit"){
        exit("Thanks for using");
    }
    $coords = explode(";", $coords);
    $coords[0] = $coords[0]-1;
    if (isset($coords[1])) {
        $coords[1] = $coords[1]-1;
    } else {
        throw new Exception("Неверно введены координаты, попробуйте снова.");
    }
    return $coords;
}

function processComputerTurn($board, $markup, &$stopGame, $db)
{
    $idGame = $board->getGameId();
    $mark = 'O';
    $answerTaked = false;
    do {
        $i = rand(0, $board->getDimension() - 1);
        $j = rand(0, $board->getDimension() - 1);
        $row = $i + 1;
        $col = $j + 1;
        try {
            $board->setMarkupOnBoard($i, $j, $markup);
            if ($board->determineWinner($i, $j) !== "") {
                $stopGame = true;
            }
            R::exec("INSERT INTO stepsInfo (
                idGame, 
                playerMark, 
                rowCoord, 
                colCoord
                ) VALUES (
                '$idGame', 
                '$mark', 
                '$row', 
                '$col')");

            $answerTaked = true;
        } catch (Exception $e) {
        }
    } while (!$answerTaked);
    return $db;
}

function inviteToContinue(&$canContinue)
{
    $db = $board->openDatabase();
    $query = R::getAll("SELECT * FROM 'gamesInfo'");
    $answer = "";
    do {
        $answer = getValue("Повторить партию? (y/n)");
        if ($answer === "y") {
            $canContinue = true;
        } elseif ($answer === "n") {
            $canContinue = false;
        }
    } while ($answer !== "y" && $answer !== "n");
}

function listGames($board)
{
    $db = $board->openDatabase();
    $query = R::query('SELECT * FROM gamesInfo');
    while ($row = $query->fetchArray()) {
        line("ID $row[0])\n    Дата:$row[1] Время: $row[2]\n    Имя игрока:$row[3]\n    Размер :$row[4]\n    Result:$row[5]");
    }
}

function replayGame($board, $id)
{
    $db = $board->openDatabase();
    $idGame = R::querySingle("SELECT EXISTS(SELECT 1 FROM gamesInfo WHERE idGame='$id')");

    if ($idGame == 1) {
        $status = R::querySingle("SELECT result from gamesInfo where idGame = '$id'");
        $query = R::query("SELECT rowCoord, colCoord, playerMark from stepsInfo where idGame = '$id'");
        $dim = R::querySingle("SELECT sizeBoard from gamesInfo where idGame = '$id'");
        $turn = 1;
        line("Статус партии: " . $status);
        $board->setDimension($dim);
        $board->initialize();
        showGameBoard($board);
        while ($row = $query->fetchArray()) {
            $board->setMarkupOnBoard($row[0] - 1, $row[1] - 1, $row[2]);
            showGameBoard($board);
        }
    } else {
        line("Партия не найдена.");
    }
}
