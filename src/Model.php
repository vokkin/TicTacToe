<?php

namespace vokkin\tic_tac_toe\Model;

use Exception as Exception;
use LogicException as LogicException;

const DEFAULT_DIMENSION = 3;
const DEFAULT_MARKUP = " ";
const PLAYER_X_MARKUP = "X";
const PLAYER_O_MARKUP = "O";

class Board
{
    private $dimension;
    private $boardArr;
    private $checkArr;
    private $userMarkup;
    private $computerMarkup;
    private $freeSpaceCount;

    public function __construct()
    {
        $this->dimension = DEFAULT_DIMENSION;
        $this->boardArr = [[]];
        $this->checkArr = [];
        $this->userMarkup = DEFAULT_MARKUP;
        $this->computerMarkup = DEFAULT_MARKUP;
        $this->freeSpaceCount = 0;
    }

    public function initialize()
    {
        $this->initializeBoardArr();
        $this->initializeCheckArr();
        $this->initializeMarkup();
        $this->initializeFreeSpace();
    }

    private function initializeBoardArr()
    {
        for ($i = 0; $i < $this->dimension; $i++) {
            for ($j = 0; $j < $this->dimension; $j++) {
                $this->boardArr[$i][$j] = DEFAULT_MARKUP;
            }
        }
    }

    private function initializeCheckArr()
    {
        for ($i = 0; $i < 2 * $this->dimension + 2; $i++) {
            $this->checkArr[$i] = 0;
        }
    }

    private function initializeMarkup()
    {
        if (rand(0, 1) == 0) {
            $this->userMarkup = PLAYER_X_MARKUP;
            $this->computerMarkup = PLAYER_O_MARKUP;
        } else {
            $this->userMarkup = PLAYER_O_MARKUP;
            $this->computerMarkup = PLAYER_X_MARKUP;
        }
    }

    private function initializeFreeSpace()
    {
        $this->freeSpaceCount = pow($this->dimension, 2);
    }

    public function determineWinner($i, $j)
    {
        if (
            $this->checkArr[$i] == $this->dimension ||
            $this->checkArr[$this->dimension + $j] == $this->dimension
        ) {
            return PLAYER_X_MARKUP;
        } elseif (
            $this->checkArr[$i] == -$this->dimension ||
            $this->checkArr[$this->dimension + $j] == -$this->dimension
        ) {
            return PLAYER_O_MARKUP;
        }
        
        if (
            $this->checkArr[2 * $this->dimension] == $this->dimension ||
            $this->checkArr[2 * $this->dimension + 1] == $this->dimension
        ) {
            return PLAYER_X_MARKUP;
        } elseif (
            $this->checkArr[2 * $this->dimension] == -$this->dimension ||
            $this->checkArr[2 * $this->dimension + 1] == -$this->dimension
        ) {
            return PLAYER_O_MARKUP;
        } else {
            return "";
        }
    }

    public function setMarkupOnBoard($i, $j, $markup)
    {
        if ($this->isCoordsCorrect($i, $j)) {
            if ($this->isSetPossible($i, $j)) {
                $this->boardArr[$i][$j] = $markup;
                $this->updateCheckArr($i, $j, $markup);
                $this->freeSpaceCount--;
            } else {
                throw new Exception("This place is already taken. Please try again.");
            }
        } else {
            throw new Exception("Incorrect coords. Please try again.");
        }
    }

    private function isCoordsCorrect($i, $j)
    {
        return is_numeric($i) && is_numeric($j) && $i >= 0 && $i < $this->dimension && $j >= 0 && $j < $this->dimension;
    }

    private function isSetPossible($i, $j)
    {
        if ($this->boardArr[$i][$j] === DEFAULT_MARKUP) {
            return true;
        } else {
            return false;
        }
    }

    private function updateCheckArr($i, $j, $markup)
    {
        $offset = 1;
        if ($markup == PLAYER_O_MARKUP) {
            $offset = -1;
        }
    
        $this->checkArr[$i] += $offset;
        $this->checkArr[$this->dimension + $j] += $offset;
    
        if (($i == $j) && ($i == ($this->dimension - 1 - $j))) {
            $this->checkArr[2 * $this->dimension] += $offset;
            $this->checkArr[2 * $this->dimension + 1] += $offset;
        } elseif ($i == $j) {
            $this->checkArr[2 * $this->dimension] += $offset;
        } elseif ($i == ($this->dimension - 1 - $j)) {
            $this->checkArr[2 * $this->dimension + 1] += $offset;
        }
    }

    public function getBoardArr()
    {
        return $this->boardArr;
    }

    public function setDimension($dim)
    {
        if (is_numeric($dim) && $dim >= 3 && $dim <= 10) {
            return $this->dimension = $dim;
        } else {
            throw new Exception("Incorrect dimension (should be 3 <= dim <= 10). Please try again.");
        }
    }

    public function getDimension()
    {
        return $this->dimension;
    }

    public function getUserMarkup()
    {
        return $this->userMarkup;
    }

    public function getComputerMarkup()
    {
        return $this->computerMarkup;
    }

    public function isFreeSpaceEnough()
    {
        return $this->freeSpaceCount !== 0;
    }
}
