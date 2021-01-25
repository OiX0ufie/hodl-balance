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
  $locale='en-US';
  $fmt = new NumberFormatter( $locale."@currency=$currency", NumberFormatter::CURRENCY );
  $currencyLabel = $fmt->getSymbol(NumberFormatter::CURRENCY_SYMBOL);

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

  $total = 0;
  $totals = [];

  foreach($accounts as $index=>$account) {
    if(!isset($totals[$account->symbol])) {
      $totals[$account->symbol] = (object)[
        'amount' => 0,
        'total' => 0,
      ];
    }
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
    $accounts[$index]->value = $value;
  }

  echo '<table class="table table-borderless table-sm mt-2">';
    echo '<thead>';
      echo '<tr>';
        echo '<th colspan="3"><h5 class="mb-0">Balance</h5></th>';
        echo '<th class="text-right">'.number_format($total, 2).' '.$currencyLabel.'</th>';
        echo '<th>total</th>';
      echo '</tr>';
      echo '<tr style="border-top: 1px solid #333; border-bottom: 1px solid #333;">';
        echo '<th>Platform</th>';
        echo '<th>Wallet</th>';
        echo '<th colspan="3">Account</th>';
      echo '</tr>';
    echo '</thead>';
    echo '<tbody style="border-bottom: 1px solid #333;">';
      foreach($accounts as $account) {
        echo '<tr>';
          echo '<td>'.$account->platform.'</td>';
          echo '<td>'.$account->wallet.'</td>';
          echo '<td>'.$account->account.'</td>';
          echo '<td class="text-right">'.number_format($account->value, 2).' '.$currencyLabel.'</td>';
          echo '<td>'.$assets[$account->symbol].' '.$account->symbol.' '.$account->amount.'</td>';
        echo '</tr>';
      }

      // show symbol stats
      echo '<tr style="border-top: 1px solid #333; border-bottom: 1px solid #333;">';
        echo '<th colspan="5"><h5 class="mb-0 mt-3">Distribution</h5></th>';
      echo '</tr>';
      uasort($totals, function($a, $b){
        if ($a->total == $b->total) {
          return 0;
        }
        return ($a->total > $b->total) ? -1 : 1;
      });
      $iteration = 0;
      foreach($totals as $symbol=>$symbolTotal) {
        $iteration++;
        $percentage = $symbolTotal->total / $total * 100;
        echo '<tr>';
          if(1 == $iteration) {
            echo '<td colspan="2" rowspan="'.sizeof($totals).'" style="position: relative;">';
              echo '<div style="position: absolute; left: 0; top: 0; width: 100%; height: 100%;">';
              if(sizeof($totals) > 0) : ?>
                <?php
                  $chartValues = [];
                  foreach($totals as $coin=>$coinTotal) {
                    $chartValues[$coin] = $coinTotal->total;
                  }
                ?>
                <canvas id="pieChart" style="width: 100%; height: 95%; margin-top: 1%"></canvas>
                <script>
                var getRandom = function(min, max) {
                  var min = Math.ceil(min);
                  var max = Math.floor(max);
                  return Math.floor(Math.random() * (max - min + 1) + min);
                }
                var getRandomRgb = function() {
                    color = getRandom(0, 255) + ', ' + getRandom(0, 255) + ', ' + getRandom(0, 255);
                    return color;
                }
                var backgroundColors = [];
                var borderColors = [];
                for(i = 0; i < <?php echo sizeof($totals); ?>; i++) {
                  var rgb = getRandomRgb();
                  backgroundColors[i] = 'rgba(' + rgb + ', 0.25)';
                  borderColors[i] = 'rgba(' + rgb + ', 1)';
                }
                console.log(backgroundColors);
                var ctx = document.getElementById('pieChart').getContext('2d');
                var pieChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: [<?php echo "'".implode("', '", array_keys($chartValues))."'"; ?>],
                        datasets: [{
                            data: [<?php echo "'".implode("', '", $chartValues)."'"; ?>],
                            backgroundColor: backgroundColors,
                            borderColor: borderColors,
                            borderWidth: 1
                        }],
                    },
                    options: {
                      legend: {
                        position: 'right'
                      }
                    }
                });
                </script>
              <?php endif;
              echo '</div>';
            echo '</td>';
          }
          echo '<td class="text-right">'.round($percentage, 2).' %</td>';
          echo '<td class="text-right">'.number_format($symbolTotal->total, 2).' '.$currencyLabel.'</td>';
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
          foreach($prices as $coin=>$price) {
            echo '<tr>';
              echo '<td class="text-right">1 '.$assets[strtoupper($coin)].' '.strtoupper($coin).'</td>';
              echo '<td>';
                if($id = $api->getIdFromSymbol($coin)) {
                  echo '<a href="https://www.coingecko.com/en/coins/'.$id->id.'/'.strtolower($currency).'" target="_blank"><i class="fa fa-chart-bar"></i></a>';
                }
              echo '</td>';
              echo '<td> = </td>';
              echo '<td class="text-right">'.number_format($price[strtolower($currency)], 8).' '.$currencyLabel.'</td>';
            echo '</tr>';
          }
        echo '</table>';
      echo '</div>';
    echo '</div>';
  echo '</div>';

  echo ob_get_clean();