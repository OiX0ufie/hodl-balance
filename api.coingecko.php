<?php

    class Api {
        private $apiBase = 'https://api.coingecko.com/api/v3';

        private function call($apiCall, $cacheDuration = 3600) {
            $apiUrl = $this->apiBase.$apiCall;
            $cacheFile = __DIR__ . '/cache/'.md5($apiUrl).'.cache';
        
            ob_start();
            $data = false;
            if(!is_writable(dirname($cacheFile))) {
              return 'Error: cannot write cache';
            }
            else {
              if(file_exists($cacheFile) && filesize($cacheFile) > 0 && (filemtime($cacheFile)+$cacheDuration) > time()) {
                $data = file_get_contents($cacheFile);
              }   
              else if($data = file_get_contents($apiUrl)) {
                file_put_contents($cacheFile, $data, LOCK_EX);
                usleep(250000);
              }
            }
            return json_decode($data);
        }

        public function getIdFromSymbol($symbol) {
            $symbol = strtolower($symbol);
            $apiCall = '/coins/list';
            if($data = $this->call($apiCall)) {
                foreach($data as $item) {
                    if($symbol == $item->symbol) {
                        return $item;
                    }
                }
                foreach($data as $item) {
                    if($symbol == $item->id) {
                        return $item;
                    }
                }
            }
            return false;
        }

        public function getSymbolFromId($id) {
            $id = strtolower($id);
            $apiCall = '/coins/list';
            if($data = $this->call($apiCall)) {
                foreach($data as $item) {
                    if($id == $item->id) {
                        return $item;
                    }
                }
            }
            return false;
        }

        public function getPrices($symbols, $currencies = 'EUR') {
            if(!is_array($currencies)) {
                $currencies = [$currencies];
            }
            foreach($currencies as $index=>$currency) {
                $currencies[$index] = strtolower($currency);
            }
            foreach($symbols as $index=>$symbol) {
                $symbols[$index] = strtolower($symbol);
            }
            $callSymbols = [];
            foreach($symbols as $symbol) {
                if($callSymbol = $this->getIdFromSymbol($symbol)) {
                    $callSymbols[$symbol] = $callSymbol->id;
                }
            }
            $apiCall = '/simple/price?ids='.implode(',', $callSymbols).'&vs_currencies='.implode(',', $currencies);
            $priceInfo = $this->call($apiCall, 60);
            $prices = [];
            foreach($symbols as $symbol) {
                foreach($currencies as $currency) {
                    $prices[$symbol][$currency] = 'n/a';
                    if(isset($callSymbols[$symbol])) {
                        foreach($priceInfo as $id=>$itemPrice) {
                            if($id == $callSymbols[$symbol]) {
                                $prices[$symbol][$currency] = $itemPrice->$currency;
                                break;
                            }
                        }
                    }
                }
            }
            return $prices;
        }

        public function getCurrencies() {
            $apiCall = '/simple/supported_vs_currencies';
            $data = $this->call($apiCall);
            if(is_array($data)) {
                foreach($data as $index=>$currency) {
                    $data[$index] = strtoupper($currency);
                }
            }
            return $data;
        }

        public function getSymbols() {
            $apiCall = '/coins/list';
            $symbols = [];
            if($data = $this->call($apiCall)) {
                if(is_array($data)) {
                    foreach($data as $symbol) {
                        $symbols[] = strtoupper($symbol->symbol);
                    }
                }
            }
            return $symbols;
        }

        // thumb, small, large
        public function getCoinImage($symbol, $size = 'thumb') {
            if($id = $this->getIdFromSymbol($symbol)) {
                if($coin = $this->getCoin($id->id)) {
                    if('large' == $size) {
                        return $coin->image->large;
                    }
                    else if('small' == $size) {
                        return $coin->image->small;
                    }
                    else {
                        return $coin->image->thumb;
                    }
                }
            }
            return false;
        }

        public function getCoin($id) {
            $apiCall = '/coins/'.$id;
            $cacheDuration = 60*60*12;  // 12 hours
            return $this->call($apiCall, $cacheDuration);
        }

        public function getCoins() {
            $apiCall = '/coins/list';
            return $this->call($apiCall);
        }

        public function listCoins() {
            $apiCall = '/coins/list';
            $data = $this->call($apiCall);
            echo str_pad('ID', 50, ' ').' ';
            echo str_pad('SYMBOL', 15, ' ').' ';
            echo str_pad('NAME', 25, ' ').' ';
            echo "\n";
            foreach($data as $item) {
                echo str_pad($item->id, 50, ' ').' ';
                echo str_pad($item->symbol, 15, ' ').' ';
                echo str_pad($item->name, 25, ' ').' ';
                echo "\n";
            }
        }
    }

    // $api = new Api();
    // $api->listCoins();
    // print_r($api->getIdFromSymbol('btc'));
    // print_r($api->getPrices(['btc', 'eth'], 'EUR'));
