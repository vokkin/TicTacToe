<?php

namespace vokkin\ticTacToe\Controller;

    use vokkin\ticTacToe\Model\Board as Board;
    use Exception as Exception;
    use LogicException as LogicException;

    use function cli\prompt;
    use function cli\line;
    use function cli\out;

    use function vokkin\ticTacToe\View\showGameBoard;
    use function vokkin\ticTacToe\View\showMessage;
    use function vokkin\ticTacToe\View\getValue;

    use const vokkin\ticTacToe\Model\PLAYER_X_MARKUP;
    use const vokkin\ticTacToe\Model\PLAYER_O_MARKUP;

function startGame()
{
    while (true) {
        $command = prompt("Enter key");
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

function play($gameBoard) {
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
        $board->setUserName(getValue("Enter user name"));
        $board->setDimension(getValue("Enter game board size"));
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

    $db->exec("INSERT INTO gamesInfo (
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

    $id = $db->querySingle("SELECT idGame FROM gamesInfo ORDER BY idGame DESC LIMIT 1");

    $board->setId($id);
    $gameId = $board->getGameId();

    do {
        showGameBoard($board);
        if ($currentMarkup == $board->getUserMarkup()) {
            $db = processUserTurn($board, $currentMarkup, $stopGame, $db);
            $endGameMsg = "Player '$currentMarkup' wins the game.";
            $currentMarkup = $board->getComputerMarkup();
        } else {
            $db = processComputerTurn($board, $currentMarkup, $stopGame, $db);
            $endGameMsg = "Player '$currentMarkup' wins the game.";
            $currentMarkup = $board->getUserMarkup();
        }

        if (!$board->isFreeSpaceEnough() && !$stopGame) {
            showGameBoard($board);
            $endGameMsg = "Draw!";
            $stopGame = true;
        }
    } while (!$stopGame);

    $temp_mark = $board->getUserMarkup();
    if ($endGameMsg == "Player '$temp_mark' wins the game."){
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
            $db->exec("INSERT INTO stepsInfo (
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
    $coords = getValue("Enter coords for player '$markup' (player: '$name' ) (enter through : )");
    if ($coords == "--exit"){
        exit("Thanks for using");
    }
    $coords = explode(":", $coords);
    $coords[0] = $coords[0]-1;
    if (isset($coords[1])) {
        $coords[1] = $coords[1]-1;
    } else {    
        throw new Exception("No second coordinate. Please try again.");
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
            $db->exec("INSERT INTO stepsInfo (
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
    $answer = "";
    do {
        $answer = getValue("Do you want to continue? (y/n)");
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
    $query = $db->query('SELECT * FROM gamesInfo');
    while ($row = $query->fetchArray()) {
        line("ID $row[0])\n    Date:$row[1] Time: $row[2]\n    Player Name:$row[3]\n    Size :$row[4]\n    Result:$row[5]");
    }
}

function replayGame($board, $id)
{
    $db = $board->openDatabase();
    $idGame = $db->querySingle("SELECT EXISTS(SELECT 1 FROM gamesInfo WHERE idGame='$id')");

    if ($idGame == 1) {
        $status = $db->querySingle("SELECT result from gamesInfo where idGame = '$id'");
        $query = $db->query("SELECT rowCoord, colCoord, playerMark from stepsInfo where idGame = '$id'");
        $dim = $db->querySingle("SELECT sizeBoard from gamesInfo where idGame = '$id'");
        $turn = 1;
        line("Game status: " . $status);
        $board->setDimension($dim);
        $board->initialize();
        showGameBoard($board);
        while ($row = $query->fetchArray()) {
            $board->setMarkupOnBoard($row[0] - 1, $row[1] - 1, $row[2]);
            showGameBoard($board);
        }
    } else {
        line("Game not found!");
    }
}
