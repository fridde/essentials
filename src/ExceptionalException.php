<?php

namespace Fridde;

class ExceptionalException extends \Exception
{
    protected $data;

    protected $texts = [
        'no_entity' => 'No entity of the class "%s" with the entity id "%s" found.',
        '' => ''
    ];

    public function __construct(string $message, array $data = [], $code = 0, Exception $previous = null) {

        $this->data = $data;

        parent::__construct($message, $code, $previous);

    }

    public function handle($exception)
    {
        $message = $this->getMessagetext($exception->getMessage(), $this->data);
        $txt = 'Uncaught exception in line ' . $this->getLine . ' of file ' . $this->getFile();
        $txt .= ': '. PHP_EOL . $message . PHP_EOL;
        $txt .= $this->getTraceAsString() . PHP_EOL . PHP_EOL;
        echo $txt;
    }

    protected function getMessageText(string $message, array $data = [])
    {
        if(substr($message, 0, 1) === ':'){
            $potential_index = strtolower(str_replace(':', '', $message));
            if(!empty($this->texts[$potential_index])){
                $message = vsprintf($this->texts[$potential_index], $data);
            }
        }
        return $message;
    }

}
