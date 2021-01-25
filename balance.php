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

  $assets = [];
  foreach($symbols as $symbol) {
    $image = $api->getCoinImage($symbol);
    if($image) {
      $image = '<img src="'.$image.'" style="height: 1em;">';
    }
    $assets[$symbol] = $image;
  }

  $colSizes = [9, 15, 18, 10];
  $fullLine = 75;

  ob_start();

  echo '<h5 class="mt-3" style="margin: 0.5rem 0.25rem 0.65rem;">Balance</h5>';
  echo '<table class="table table-borderless table-sm">';
    echo '<thead><tr style="border-top: 1px solid #333; border-bottom: 1px solid #333;">';
      echo '<th>Platform</th>';
      echo '<th>Wallet</th>';
      echo '<th>Account</th>';
      echo '<th class="text-right">'.$currency.'</th>';
      echo '<th>Amount</th>';
    echo '</tr></thead>';

    $total = 0;
    $totals = [];
    echo '<tbody style="border-bottom: 1px solid #333;">';
      foreach($accounts as $account) {
        if(!isset($totals[$account->symbol])) {
          $totals[$account->symbol] = (object)[
            'amount' => 0,
            'total' => 0,
          ];
        }
        echo '<tr>';
          echo '<td>'.$account->platform.'</td>';
          echo '<td>'.$account->wallet.'</td>';
          echo '<td>'.$account->account.'</td>';
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
          echo '<td class="text-right">'.number_format($value, 2).'</td>';
          echo '<td>'.$assets[$account->symbol].' '.$account->symbol.' '.$account->amount.'</td>';
        echo '</tr>';
      }
      // show total
      echo '<tr style="border-top: 1px solid #333;">';
        echo '<th colspan="2"></th>';
        echo '<th class="text-right" colspan="2">'.$currency.' '.number_format($total, 2).'</th>';
        echo '<th>total </th>';
      echo '</tr>';

      // show symbol stats
      echo '<tr style="border-bottom: 1px solid #333;"><th colspan="5"><h5 class="mt-3">Crypto distribution</h5></th></tr>';
      uasort($totals, function($a, $b){
        if ($a->total == $b->total) {
          return 0;
        }
        return ($a->total > $b->total) ? -1 : 1;
      });
      foreach($totals as $symbol=>$symbolTotal) {
        $percentage = $symbolTotal->total/$total*100;
        echo '<tr>';
          echo '<td colspan="3" class="text-right" style="position: relative;">';
            echo '<div style="position: absolute; left: 0.75rem; top: 0.42rem; height: 0.7em; background: rgba(255, 255, 255, 0.75); width: '.round($percentage/100*70, 2).'%;"></div>';
            echo round($percentage, 1).'% </td>';
          echo '<td class="text-right">'.number_format($symbolTotal->total, 2).'</td>';
          echo '<td>'.$assets[$symbol].' '.$symbol.' '.$symbolTotal->amount.'</td>';
        echo '</tr>';
      }
    echo '</tbody>';
  echo '</table>';


  echo '<div class="container mt-5">';
    echo '<div class="row">';
      echo '<div class="col">';
        echo '<p>Last updated</p>';
        echo '<p><small>Balance config '.(is_numeric($lastUpdate) ? date('j.n.Y H:i', $lastUpdate) : 'unknown date').' '.date_default_timezone_get().'</small><br>';
        echo '<small>Coingecko API  '.date('j.n.Y H:i').' '.date_default_timezone_get().'</small></p>';
      echo '</div>';
      echo '<div class="col">';
        // print general price info
        uasort($prices, function($a, $b) use ($currency) {
          if ($a[strtolower($currency)] == $b[strtolower($currency)]) {
            return 0;
          }
          return ($a[strtolower($currency)] > $b[strtolower($currency)]) ? -1 : 1;
        });
        echo '<table class="table table-sm table-borderless" style="width: auto;">';
          echo '<tr><th colspan="3">Coin</th><th class="text-right">'.$currency.'</th>';
          foreach($prices as $coin=>$price) {
            echo '<tr>';
              echo '<td class="text-right">1 '.$assets[strtoupper($coin)].' '.strtoupper($coin).'</td>';
              echo '<td>';
                if($id = $api->getIdFromSymbol($coin)) {
                  echo '<a href="https://www.coingecko.com/en/coins/'.$id->id.'/'.strtolower($currency).'" target="_blank"><i class="fa fa-chart-bar"></i></a>';
                }
              echo '</td>';
              echo '<td> = </td>';
              echo '<td class="text-right">'.number_format($price[strtolower($currency)], 8).'</td>';
            echo '</tr>';
          }
        echo '</table>';
      echo '</div>';
    echo '</div>';
  echo '</div>';

  echo ob_get_clean();