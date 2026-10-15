<?php
include_once __DIR__.'/pg24pay_sign.php';
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of veintequatropago_request
 *
 * @author 24-pay
 */
class Pg24payRequest {
    private $signGenerator;
    function __construct() {
        $this->signGenerator = new Pg24paySign('EUR');
    }
    
    public function getServiceDomain(){
        if ($this->signGenerator->debug==1)
            return "https://test.24-pay.eu";
        else
            return "https://admin.24-pay.eu";
    }
    
    public function getCheckUrl() {
        return $this->getServiceDomain() . '/pay_gate/check';
    }
    
    public function getInstallUrl() {
        return $this->getServiceDomain() . '/pay_gate/install';
    }
        
    public function checkSignGeneration() {
        $checksum = $this->signGenerator->getSign('Check sign text for MID ' . $this->signGenerator->mid);
        $status = $this->makePostRequest($this->getCheckUrl(), array(
                'CHECKSUM' => $checksum ,
                'MID' => $this->signGenerator->mid
        ));
        return $status;
    }
    
    public function checkAvailableGateways() {
        $availableGateways = $this->makePostRequest($this->getInstallUrl(), array(
                'ESHOP_ID' => $this->signGenerator->eshopid,
                'MID' => $this->signGenerator->mid
        ));
        return $availableGateways;
    }
    
    public function makePostRequest($url, $data) {
        // staging gateway does not have verified certificate
        $verify = $this->signGenerator->debug != 1;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => $verify,
                CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            ));
            $result = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($result === false) {
                throw new Exception($error ?: 'Request failed');
            }
            return $result;
        }

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'timeout' => 15,
                'ignore_errors' => true,
            ),
        );
        if (!$verify) {
            $options['ssl'] = array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            );
        }
        $result = @file_get_contents($url, false, stream_context_create($options));
        if ($result === false) {
            throw new Exception('Request to ' . $url . ' failed');
        }
        return $result;
    }
}
