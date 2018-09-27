<?php

// Running into trouble? 
// Email your request with the debug parameter set to true 
// to support@rocketship.it along with a brief description
// of the problem.

// Documentation: https://docs.rocketship.it/2-0/

namespace RocketShipIt\RocketShipIt;

class RocketShipIt
{
    public $response = array();
    public $binWorkingDir = '';
    public $binPath = '';
    public $command = './RocketShipIt';
    public $apiKey = '';
    public $options = array(
        // change to your own endpoint if running as a service
        'http_endpoint' => 'https://api.rocketship.it/v1/',
        //'http_endpoint' => 'http://localhost:8080/api/v1/',
    );

    public function __construct()
    {
        $this->binWorkingDir = __DIR__. '/../../';
        $this->binPath = $this->binWorkingDir. 'RocketShipIt';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->binPath = $this->binPath. '.exe';
            $this->command = 'RocketShipIt.exe';
        }
    }

    public function request($params)
    {
        if ($this->apiKey != '') {
            return $this->httpRequest($params);
        }
        
        return $this->irequest($params);
    }

    public function irequest($data)
    {
        if (file_exists(__DIR__. '/RocketShipIt')) {
            $this->binWorkingDir = __DIR__.'/';
            $this->binPath = __DIR__. '/RocketShipIt';
        }

        if (!file_exists($this->binPath)) {
            return array(
                'meta' => array(
                    'code' => 500,
                    'error_message' => 'RocketShipIt binary file is missing.  Please make sure to upload all files.',
                ),
            );
        }

        return $this->binstubRequest($data);
    }

    public function httpRequest($data)
    {
        $dataString = json_encode($data);

        $ch = curl_init($this->options['http_endpoint']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'x-api-key: '. $this->apiKey,
            'Content-Type: application/json',
            )
        );

        $result = curl_exec($ch);

        $resp = json_decode($result, true);
        if (!$resp) {
            return array(
                'meta' => array(
                    'code' => 500,
                    'error_message' => 'Unable to parse JSON, got: '. $result,
                ),
            );
        }

        $this->response = $resp;

        return $this->response;
    }

    public function binstubRequest($data)
    {
        $descriptorspec = array(
           0 => array('pipe', 'r'),  // stdin is a pipe that the child will read from
           1 => array('pipe', 'w'),  // stdout is a pipe that the child will write to
           2 => array('pipe', 'w')   // stderr is a file to write to
        );

        $pipes = array();

        if (!is_executable($this->binPath)) {
            chmod($this->binPath, 0755);
        }

        if (!is_executable($this->binPath)) {
            return array(
                'meta' => array(
                    'code' => 500,
                    'error_message' => 'RocketShipIt binary is missing or not executable.',
                ),
            );
        }

        $process = proc_open($this->command, $descriptorspec, $pipes, $this->binWorkingDir);

        if (is_resource($process)) {
            // send the data via stdin to RocketShipIt
            fwrite($pipes[0], json_encode($data));
            fclose($pipes[0]);

            // response from RocketShipIt
            $result = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $returnValue = proc_close($process);
        }

        $resp = json_decode($result, true);
        if (!$resp) {
            return array(
                'meta' => array(
                    'code' => 500,
                    'error_message' => 'Unable to communicate with RocketShipIt binary or parse JSON, got: '. $result. ' '. $errors,
                ),
            );

        }

        $this->response = $resp;

        return $this->response;
    }
}

