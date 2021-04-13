<?php

  require_once(__DIR__.'/config.php');

  function number_format_nice($num, $decimals = 0, $decimalSeparator = '.', $thousandsSeparator = ',') {
    $numberString = number_format($num, $decimals, $decimalSeparator, $thousandsSeparator);
    if(strpos($numberString, $decimalSeparator) !== false) {
      $numberString = rtrim(rtrim($numberString, '0'), '.');
    }
    return $numberString;
  }

  $now = time();

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
      $image = '<img src="'.$image.'" style="height: 1rem; margin: -3px 0 0 0;">';
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
      // show symbol stats
      echo '<tr style="border-top: 1px solid #333; border-bottom: 1px solid #333;">';
        echo '<th colspan="2"><h5 class="mb-0">Funds</h5></th>';
        echo '<th class="text-right text-bigger">'.number_format($total, 2).' '.$currencyLabel.'</th>';
        echo '<th><a class="float-right" href="#" onclick="$(\'.walletContents\').toggle(); return false;" title="toggle wallet contents"><i class="fa fa-wallet"></i></a>total</th>';
      echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

      // wallet contents
      echo '<tr><td colspan="4" class="walletContents">';
        echo '<table class="table table-borderless table-sm">';
          echo '<thead>';
            echo '<tr style="border-bottom: 1px solid #333;">';
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
                echo '<td class="text-right text-bigger">'.number_format($account->value, 2).' '.$currencyLabel.'</td>';
                echo '<td>'.$assets[$account->symbol].' '.$account->symbol.' <span class="text-secondary">'.$account->amount.'</span></td>';
              echo '</tr>';
            }
          echo '</tbody>';
        echo '</table>';
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
            echo '<td rowspan="'.sizeof($totals).'" style="position: relative; width: 42%;">';
              echo '<div style="position: absolute; left: 0; top: 0; width: 100%; height: 100%;">';
              if(sizeof($totals) > 0) : ?>
                <?php
                  $chartValues = [];
                  foreach($totals as $coin=>$coinTotal) {
                    $chartValues[$coin] = $coinTotal->total;
                  }
                ?>
                <canvas id="pieChart" style="width: 100%; height: 95%; margin-top: 0.5em;"></canvas>
                <script>
                var borderColors = [];
                for(i = 0; i < <?php echo sizeof($totals); ?>; i++) {
                  borderColors[i] = '#191d21';
                }
                var ctx = document.getElementById('pieChart').getContext('2d');
                var pieChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: [<?php echo "'".implode("', '", array_keys($chartValues))."'"; ?>],
                        datasets: [{
                            data: [<?php echo "'".implode("', '", $chartValues)."'"; ?>],
                            borderColor: borderColors,
                            borderWidth: 1
                        }],
                    },
                    options: {
                      legend: {
                        position: 'right'
                      },
                      plugins: {
                        colorschemes: {
                          scheme: 'office.Studio6' //office.Atlas6'
                        }
                      }
                    }
                });
                </script>
              <?php endif;
              echo '</div>';
            echo '</td>';
          }
          echo '<td class="text-right text-secondary align-middle">'.round($percentage, 1).' %</td>';
          echo '<td class="text-right text-bigger align-middle">'.number_format($symbolTotal->total, 2).' '.$currencyLabel.'</td>';
          echo '<td class="align-middle">'.$assets[$symbol].' '.$symbol.' <span class="text-secondary">'.$symbolTotal->amount.'</span></td>';
        echo '</tr>';
      }
    echo '</tbody>';
  echo '</table>';

  echo '<div class="container mt-3">';
    echo '<h5 class="">Market data</h5>';
    echo '<div class="row" style="border-top: 1px solid #333; border-bottom: 1px solid #333;">';
      echo '<div class="col">';
        // print general price info
        uasort($prices, function($a, $b) use ($currency) {
          if ($a[strtolower($currency)] == $b[strtolower($currency)]) {
            return 0;
          }
          return ($a[strtolower($currency)] > $b[strtolower($currency)]) ? -1 : 1;
        });
        echo '<table class="table table-hover table-sm table-borderless" id="marketData">';
          echo '<thead>';
            echo '<tr style="border-bottom: 1px solid #333; border-bottom: 1px solid #333;">';
              echo '<th colspan="2">Coin/Token</th>';
              echo '<th class="text-right">Value</th>';
              echo '<th class="text-right">24h</th>';
              echo '<th class="text-right">7d</th>';
              echo '<th class="text-right">30d</th>';
              echo '<th class="text-right">60d</th>';
              echo '<th><div class="d-none d-lg-block">ATH</div></th>';
              echo '<th></th>';
              echo '<th></th>';
              echo '<th class="text-center">Rank</th>';
              echo '<th class="text-center">Circulating</th>';
              echo '<th class="text-right" title="Value per circulating coin/token if total supply was 100,000,000"><i class="fa fa-coins"></i> <small><i class="far fa-question-circle"></i></small></th>';
              echo '<th></th>';
            echo '</tr>';
          echo '<thead>';
          echo '<tbody>';
            foreach($prices as $coin=>$price) {
              $coinMeta = $api->getIdFromSymbol($coin);
              $coinInfo = $api->getCoin($coinMeta->id);
              echo '<tr>';
                echo '<td class="text-right">';
                  if($coinMeta) {
                    echo '<a href="https://www.coingecko.com/en/coins/'.$coinMeta->id.'/'.strtolower($currency).'" target="_blank">';
                  }
                  echo '1 '.$assets[strtoupper($coin)].' '.strtoupper($coin);
                  if($coinMeta) {
                    echo '</a>';
                  }
                echo '</td>';
                echo '<td> = </td>';
                echo '<td class="text-right" data-text="'.number_format($price[strtolower($currency)], 8).'">'.number_format_nice($price[strtolower($currency)], 8).'&nbsp;'.$currencyLabel.'</td>';
                $percRanges = [
                  'price_change_percentage_24h',
                  'price_change_percentage_7d',
                  'price_change_percentage_30d',
                  'price_change_percentage_60d',
                ];
                foreach($percRanges as $perc) {
                  echo '<td class="text-right">';
                    if($coinInfo) {
                      $change = (float) $coinInfo->market_data->$perc;
                      $changeClass = 'text-secondary';
                      if($change > 0) {
                        $changeClass = 'text-success';
                      }
                      else if($change < 0) {
                        $changeClass = 'text-danger';
                      }
                      echo '<small class="'.$changeClass.'">'.number_format($change, 1);
                      echo '&nbsp;%</small>';
                    }
                  echo '</td>';
                }

                // ATH time info
                $agoValue = '';
                $agoString = '';
                if($coinInfo) {
                  $lastAth = strtotime($coinInfo->market_data->ath_date->{strtolower($currency)});
                  $agoValue = $lastAth;
                  $ago = ($now-$lastAth)/60/60;
                  if($ago > 48) {
                    $ago /= 24;
                    if($ago > 30) {
                      if($ago/30 > 12) {
                        $ago = round($ago/365, 1);
                        $agoLabel = 'years ago';
                      }
                      else {
                        $ago = round($ago/30, 1);
                        $agoLabel = 'months ago';
                      }
                    }
                    else {
                      $ago = round($ago);
                      $agoLabel = 'days ago';
                    }
                  }
                  else {
                    $ago = round($ago);
                    $agoLabel = 'hours ago';
                  }
                  $agoString = '<span title="'.date('j.n.Y H:i', $lastAth).' '.date_default_timezone_get().'">'.$ago.' <small>'.$agoLabel.'</small></span>';
                }
                echo '<td data-text="'.$agoValue.'">';
                  if(!empty($agoString)) {
                    echo '<div class="d-none d-lg-block">'.$agoString.'</div>';
                  }
                echo '</td>';

                // ATH value
                $athValue = '';
                $athString = '';
                if($coinInfo) {
                  $athValue = $coinInfo->market_data->ath->{strtolower($currency)};
                  $athString .= '<small>'.number_format_nice($coinInfo->market_data->ath->{strtolower($currency)}, 8);
                  $athString .= '&nbsp;'.$currencyLabel.'</small>';
                }
                echo '<td data-text="'.$athValue.'" class="text-right">';
                  if(!empty($athString)) {
                    echo '<div class="d-none d-lg-block">'.$athString.'</div>';
                  }
                echo '</td>';

                // ATH percentage
                $athValue = '';
                $athString = '';
                if($coinInfo) {
                  $athValue = $coinInfo->market_data->ath_change_percentage->{strtolower($currency)};
                  $athString = '<span class="text-info">'.number_format($coinInfo->market_data->ath_change_percentage->{strtolower($currency)}, 1).'&nbsp;%</span>';
                }
                echo '<td data-text="'.$athValue.'" class="text-right">';
                  if(!empty($athString)) {
                    echo '<div class="d-none d-lg-block">'.$athString.'</div>';
                  }
                echo '</td>';
                echo '<td class="text-right">';
                  if($coinInfo) {
                    if($rank = $coinInfo->market_data->market_cap_rank) {
                      $rankClass = 'text-secondary';
                      if($rank <= 10) {
                        $rankClass = 'text-success';
                      }
                      else if($rank <= 25) {
                        $rankClass = 'text-warning';
                      }
                      echo '<span class="'.$rankClass.'">'.$rank.'</span>';
                    }
                  }
                echo '</td>';
                echo '<td class="text-center">';
                  if($coinInfo) {
                    $totalSupply = $coinInfo->market_data->total_supply;
                    $circulatingSupply = $coinInfo->market_data->circulating_supply;
                    if($totalSupply > 0 && $circulatingSupply > 0) {
                      echo '<span title="'.number_format($circulatingSupply).' circulating / '.number_format($totalSupply).' total">'.number_format($circulatingSupply/$totalSupply*100).'&nbsp;%</span>';
                    }
                    else if($circulatingSupply > 0) {
                      echo '<span title="'.number_format($circulatingSupply).' circulating">Σ</span>';
                    }
                  }
                echo '</td>';

                $colValue = '';
                $colString = '';
                if($coinInfo) {
                  if($circulatingSupply > 0 && $price[strtolower($currency)] > 0) {
                    $colValue = number_format_nice($price[strtolower($currency)] * $circulatingSupply / 100000000);
                    $colString .= '<small class="text-secondary">'.number_format_nice($price[strtolower($currency)] * $circulatingSupply / 100000000);
                    $colString .= '&nbsp;'.$currencyLabel.'</small>';
                  }
                }                
                echo '<td class="text-right" data-text="'.$colValue.'">';
                  if(!empty($colString)) {
                    echo $colString;
                  }
                echo '</td>';

                echo '<td>';
                  echo '<a class="coinInfoPopToggle" href="#" onclick="return false;"><i class="fa fa-link"></i></a>';
                  echo '<div class="coinInfoPop" onclick="$(this).hide();">';
                    echo '<a href="#" class="float-right" onclick="$(this).parent().hide(); return false;"><i class="fa fa-2x fa-times"></i></a>';
                    echo '<strong>'.$coinInfo->name.'</strong> ('.strtoupper($coinInfo->symbol).')';

                    echo '<div class="row links">';
                      echo '<div class="col">';
                        ob_start();
                        foreach($coinInfo->links->homepage as $site) {
                          if(!empty($site)) {
                            echo '<a href="'.$site.'" target="_blank"><i class="fa fa-link"></i></a> ';
                          }
                        }
                        foreach($coinInfo->links->announcement_url as $site) {
                          if(!empty($site)) {
                            echo '<a href="'.$site.'" target="_blank"><i class="fa fa-bullhorn"></i></a> ';
                          }
                        }
                        $out = ob_get_clean();
                        if(!empty($out))  {
                          echo '<span>Websites</span>';
                          echo $out;
                        }

                        // forums and chat
                        ob_start();
                        foreach($coinInfo->links->official_forum_url as $site) {
                          if(!empty($site)) {
                            echo '<a href="'.$site.'" target="_blank"><i class="fa fa-users"></i></a> ';
                          }
                        }
                        foreach($coinInfo->links->chat_url as $site) {
                          if(!empty($site)) {
                            echo '<a href="'.$site.'" target="_blank"><i class="far fa-comment"></i></a> ';
                          }
                        }
                        if(!empty($coinInfo->links->bitcointalk_thread_identifier)) {
                          echo '<a href="https://bitcointalk.org/index.php?topic='.$coinInfo->links->bitcointalk_thread_identifier.'" target="_blank"><i class="fab fa-bitcoin"></i></a> ';
                        }
                        $out = ob_get_clean();
                        if(!empty($out))  {
                          echo '<span>Forums and chat</span>';
                          echo $out;
                        }
                        
                        // social networks
                        ob_start();
                        if(!empty($coinInfo->links->twitter_screen_name)) {
                          echo '<a href="https://nitter.dark.fail/'.$coinInfo->links->twitter_screen_name.'" target="_blank"><i class="fab fa-twitter"></i></a> ';
                        }
                        if(!empty($coinInfo->links->facebook_username)) {
                          echo '<a href="https://facebook.com/'.$coinInfo->links->facebook_username.'" target="_blank"><i class="fab fa-facebook-f"></i></a> ';
                        }
                        if(!empty($coinInfo->links->subreddit_url)) {
                          echo '<a href="'.$coinInfo->links->subreddit_url.'" target="_blank"><i class="fab fa-reddit-alien"></i></a> ';
                        }
                        $out = ob_get_clean();
                        if(!empty($out))  {
                          echo '<span>Social networks</span>';
                          echo $out;
                        }
                      
                        echo '</div>';
                      echo '<div class="col">';

                        // blockchain sites
                        ob_start();
                        foreach($coinInfo->links->blockchain_site as $site) {
                          if(!empty($site)) {
                            echo '<a href="'.$site.'" target="_blank"><i class="fa fa-database"></i></a> ';
                          }
                        }
                        $out = ob_get_clean();
                        if(!empty($out))  {
                          echo '<span>Blockchain sites</span>';
                          echo $out;
                        }

                        // repos
                        ob_start();
                        foreach($coinInfo->links->repos_url->github as $site) {
                          if(!empty($site)) {
                            echo '<a href="'.$site.'" target="_blank"><i class="fab fa-github"></i></a> ';
                          }
                        }
                        foreach($coinInfo->links->repos_url->bitbucket as $site) {
                          if(!empty($site)) {
                            echo '<a href="'.$site.'" target="_blank"><i class="fab fa-bitbucket"></i></a> ';
                          }
                        }
                        $out = ob_get_clean();
                        if(!empty($out))  {
                          echo '<span>Repos</span>';
                          echo $out;
                        }

                      echo '</div>';
                    echo '</div>';

                    if(!empty($coinInfo->description->en)) {
                      echo '<p class="description"><span class="text-secondary">'.substr($coinInfo->description->en, 0, 300).'...</span> More info at <a href="https://www.coingecko.com/en/coins/'.$coinMeta->id.'" target="_blank">coingecko.com/'.$coinMeta->id.'</a></p>';
                    }
                  echo '</div>';
                echo '</td>';
              echo '</tr>';
            }
          echo '</tbody>';
        echo '</table>';
      echo '</div>';
    echo '</div>';
  ?>
    <script>
      $('.coinInfoPopToggle').click(function() {
        $('.coinInfoPop').hide();
        $(this).next().show();
      });
      $('.coinInfoPop a').click(function(event) {
        event.stopPropagation();
      });

      $(function() {
        $("#marketData").tablesorter({
          usNumberFormat: true
        });
      });
    </script>
  <?php

    echo '<div class="row mt-5 mb-3">';
      echo '<div class="col">';
        echo 'Last updated';
        echo '<div class="row">';
          echo '<div class="col"><small><span class="text-secondary">Page rendered</span> '.date('j.n.Y H:i', $now).' '.date_default_timezone_get().'</small></div>';
          echo '<div class="col"><small><span class="text-secondary">Balance config</span> '.(is_numeric($lastUpdate) ? date('j.n.Y H:i', $lastUpdate) : 'unknown date').' '.date_default_timezone_get().'</small></div>';
          echo '<div class="col"><small><span class="text-secondary">Coingecko API</span>  '.date('j.n.Y H:i').' '.date_default_timezone_get().'</small></div>';
        echo '</div>';
      echo '</div>';
    echo '</div>';
  echo '</div>';

  echo ob_get_clean();