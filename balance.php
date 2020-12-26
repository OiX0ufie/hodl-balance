<?php

  require_once(__DIR__.'/config.php');

  if(!isset($_GET['key'])) {
    die('missing encryption key');
  }
  if(!isset($_COOKIE['hodl'])) {
    die('missing configuration');
  }

  use Nullix\CryptoJsAes\CryptoJsAes;
  if(isset($_COOKIE['hodl'])) {
      require_once(__DIR__.'/CryptoJsAes.php');
      $hodl = $_COOKIE['hodl'];
      // update cookie lifetime
      setcookie('hodl', $hodl, strtotime('+1 year'), '/');
      $decrypted = json_decode(CryptoJsAes::decrypt($hodl, $_GET['key']));
      if(!$decrypted) {
        die('configuration error');
      }
  }

  $json = $decrypted;

  require_once(__DIR__.'/api.coingecko.php');
  $api = new Api();

  // check currency
  $supportedCurrencies = $api->getCurrencies();
  $currency = 'EUR';
  if(property_exists($json, 'currency')) {
    if(in_array(strtoupper($json->currency), $supportedCurrencies)) {
      $currency = strtoupper($json->currency);
    }
  }

  // check lastupdate date
  $lastUpdate = false;
  if(property_exists($json, 'lastUpdate')) {
    $lastUpdate = $json->lastUpdate;
    if(is_numeric($lastUpdate)) {
      $lastUpdate = round($lastUpdate/1000);
    }
  }
  
  $accounts = $json->entries;

  // get symbol prices
  $symbols = [];
  foreach($accounts as $account) {
    if(!in_array($account->symbol, $symbols)) {
      $symbols[] = $account->symbol;
    }
  }
  $prices = $api->getPrices($symbols, $currency);
  // print_r($prices);
  // die();

  $colSizes = [9, 15, 18, 10];
  $fullLine = 75;

  ob_start();
  echo "\n".str_pad('PLATFORM', $colSizes[0], ' ');
  echo str_pad('WALLET', $colSizes[1], ' ');
  echo str_pad('ACCOUNT', $colSizes[2], ' ');
  echo str_pad($currency, $colSizes[3], ' ', STR_PAD_LEFT).'   ';
  echo 'AMOUNT'."\n";
  echo str_repeat('-', $fullLine)."\n";
  $total = 0;
  $totals = [];
  foreach($accounts as $account) {
    if(!isset($totals[$account->symbol])) {
      $totals[$account->symbol] = (object)[
        'amount' => 0,
        'total' => 0,
      ];
    }
    echo str_pad($account->platform, $colSizes[0], ' ');
    echo str_pad($account->wallet, $colSizes[1], ' ');
    echo str_pad($account->account, $colSizes[2], ' ');
    $price = $prices[strtolower($account->symbol)][strtolower($currency)];
    if(is_numeric($price)) {
      $value = round($price*$account->amount, 2);
      $total += $value;
      $totals[$account->symbol]->amount += $account->amount;
      $totals[$account->symbol]->total += $value;
    }
    else {
      $value = $price;
    }
    echo str_pad($value, $colSizes[3], ' ', STR_PAD_LEFT).'  ';
    echo $account->symbol.' '.$account->amount;
    echo "\n";
  }
  // show total
  echo str_repeat('-', $fullLine)."\n";
  echo str_repeat(' ', $colSizes[0]);
  echo str_repeat(' ', $colSizes[1]);
  echo str_pad($currency, $colSizes[2], ' ', STR_PAD_LEFT);
  echo str_pad($total, $colSizes[3], ' ', STR_PAD_LEFT).'  TOTAL'."\n";

  // show symbol stats
  uasort($totals, function($a, $b){
    if ($a->total == $b->total) {
      return 0;
    }
    return ($a->total > $b->total) ? -1 : 1;
  });
  echo "\n";
  foreach($totals as $symbol=>$symbolTotal) {
    echo str_pad('', $colSizes[0], ' ');
    $barSize = $colSizes[1]-2;
    $percentage = $symbolTotal->total/$total*100;
    $barsFull = round($percentage/100*$barSize);
    if($barsFull > $barSize) {
      $barsFull = $barSize;
    }
    $barsEmpty = $barSize - $barsFull;
    echo '['.str_repeat('=', $barsFull).str_repeat(' ', $barsEmpty).']';
    echo str_pad(round($percentage, 1)."%   ".$currency, $colSizes[2], ' ', STR_PAD_LEFT);
    echo str_pad($symbolTotal->total, $colSizes[3], ' ', STR_PAD_LEFT).'  ';
    echo $symbol.' '.$symbolTotal->amount."\n";
  }

  echo str_repeat('-', $fullLine)."\n";
  uasort($prices, function($a, $b) use ($currency) {
    if ($a[strtolower($currency)] == $b[strtolower($currency)]) {
      return 0;
    }
    return ($a[strtolower($currency)] > $b[strtolower($currency)]) ? -1 : 1;
  });
  $lastUpdatedInfo = [
    'LAST UPDATED',
    'Balance config '.(is_numeric($lastUpdate) ? date('j.n.Y H:i', $lastUpdate) : 'unknown date'),
    'Coingecko API  '.date('j.n.Y H:i')
  ];
  $cols = $colSizes[0]+$colSizes[1]+$colSizes[2]+$colSizes[3]+3;
  foreach($prices as $coin=>$price) {
    $priceLine = str_pad('1 '.strtoupper($coin).' = ', $cols, ' ', STR_PAD_LEFT);
    $priceLine .= str_pad(number_format($price[strtolower($currency)], 8).' '.$currency, $fullLine-$cols, ' ', STR_PAD_LEFT);

    if($updatedInfo = array_shift($lastUpdatedInfo)) {
      echo $updatedInfo;
      echo substr($priceLine, strlen($updatedInfo));
    }
    else {
      echo $priceLine;
    }
    echo "\n";
  }
  foreach($lastUpdatedInfo as $updatedInfo) {
    echo $updatedInfo."\n";
  }

  $data = ob_get_clean();

  $cmd = 'echo '.escapeshellarg(trim($data)).' | '.$_CONFIG['balanceCommand'];
  $output = shell_exec($cmd);
  if(empty($output)) {
    die('no response');
  }

  echo $output;