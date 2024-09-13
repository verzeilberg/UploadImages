<?php

namespace UploadImages\Exception;

use Exception;

class DeleteFileException extends Exception
{

    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public function customMessage()
    {
        //error message
        return 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
            . ': <b>' . $this->getMessage() . '</b> while deleting a file.';
    }

}
