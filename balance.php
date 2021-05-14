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
  $assets = [];
  $coins = [];
  foreach($accounts as $account) {
    $account->value = 0;
    $account->symbol = strtolower($account->symbol);
    if($coin = $api->getIdFromSymbol($account->symbol)) {
      $account->symbol = $coin->id;
    }
    if(!isset($assets[$account->symbol])) {
      $assets[$account->symbol] = (object) [
        'id' => false,
        'symbol' => false,
        'name' => false,
        'image_thumb' => false,
        'amount' => 0,
        'value' => 0
      ];
    }
    $assets[$account->symbol]->amount += $account->amount;
    if($coin) {
      $assets[$account->symbol]->id = $coin->id;
      $assets[$account->symbol]->symbol = $coin->symbol;
      $assets[$account->symbol]->name = $coin->name;
      if($image = $api->getCoinImage($coin->id)) {
        $assets[$account->symbol]->image_thumb = '<img src="'.$image.'" style="height: 1rem; margin: -3px 0 0 0;">';
      }
      $coins[$coin->id] = $coin->id;
    }
  }
  $prices = [];
  if(sizeof($coins) > 0) {
    $prices = $api->getPrices($coins, $currency);
  }
  unset($coins);

  $totalValue = 0;
  foreach($accounts as $index=>$account) {
    if(isset($prices[$account->symbol]) && isset($prices[$account->symbol][strtolower($currency)])) {
      $price = $prices[$account->symbol][strtolower($currency)];
      if(is_numeric($price)) {
        $value = round($price * $account->amount, 2);
        $accounts[$index]->value += $value;
        $assets[$account->symbol]->value += $value;
        $totalValue += $value;
      }
    }
   }

  // prepare platforms and wallet totals
  $platforms = [];
  $wallets = [];
  foreach($accounts as $account) {
    $platformKey = sha1($account->platform);
    if(!isset($platforms[$platformKey])) {
      $platforms[$platformKey] = (object)[
        'platform' => $account->platform,
        'coins' => [],
        'value' => 0,
      ];
    }
    if(!isset($platforms[$platformKey]->coins[$account->symbol])) {
      $platforms[$platformKey]->coins[$account->symbol] = 0;
    }
    $platforms[$platformKey]->coins[$account->symbol] += $account->amount;
    $platforms[$platformKey]->value += $account->value;

    $walletKey = sha1($account->platform.'/'.$account->wallet);
    if(!isset($wallets[$walletKey])) {
      $wallets[$walletKey] = (object)[
        'platform' => $account->platform,
        'wallet' => $account->wallet,
        'coins' => [],
        'value' => 0,
      ];
    }
    if(!isset($wallets[$walletKey]->coins[$account->symbol])) {
      $wallets[$walletKey]->coins[$account->symbol] = 0;
    }
    $wallets[$walletKey]->coins[$account->symbol] += $account->amount;
    $wallets[$walletKey]->value += $account->value;
  }
  uasort($platforms, function($a, $b){
    if ($a->value == $b->value) {
      return 0;
    }
    return ($a->value > $b->value) ? -1 : 1;
  });
  uasort($wallets, function($a, $b){
    if ($a->value == $b->value) {
      return 0;
    }
    return ($a->value > $b->value) ? -1 : 1;
  });

  echo '<table class="table table-borderless table-sm mt-2">';
    echo '<thead>';
      // show symbol stats
      echo '<tr style="border-top: 1px solid #333; border-bottom: 1px solid #333;">';
        echo '<th colspan="2"><h5 class="mb-0">Funds</h5></th>';
        echo '<th class="text-right text-bigger">'.number_format($totalValue, 2).' '.$currencyLabel.'</th>';
        echo '<th><a class="float-right" href="#" onclick="$(\'.walletContents\').toggle(); return false;" title="toggle wallet contents"><i class="fa fa-wallet"></i></a>total</th>';
      echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

      // wallet contents
      echo '<tr><td colspan="4" class="walletContents">';
        echo '<table class="table table-hover table-striped table-sm">';
          echo '<thead>';
            echo '<tr style="border-bottom: 1px solid #333;">';
              echo '<th>Platform</th>';
              echo '<th>Wallet</th>';
              echo '<th colspan="4">Account</th>';
            echo '</tr>';
          echo '</thead>';
          echo '<tbody style="border-bottom: 1px solid #333;">';

            // print platform distribution
            echo '<tr><th colspan="5" class="text-secondary">Platforms</th></tr>';
            foreach($platforms as $platform) {
              echo '<tr>';
                echo '<td class="align-bottom">'.$platform->platform.'</td>';
                echo '<td class="align-bottom text-secondary"><small>Σ</small></td>';
                echo '<td class="align-bottom text-secondary"><small>Σ</small></td>';
                echo '<td class="text-right align-bottom text-secondary">'.($totalValue > 0 ? number_format($platform->value / $totalValue * 100, 1) : '-').' %</td>';
                echo '<td class="text-right text-bigger">'.number_format($platform->value, 2).' '.$currencyLabel.'</td>';
                echo '<td class="align-bottom text-secondary"><small>';
                  $coins = array_keys($platform->coins);
                  foreach($coins as $index=>$coin) {
                    if(false !== $assets[$coin]->symbol) {
                      $coins[$index] = strtoupper($assets[$coin]->symbol);
                    }
                  }
                  sort($coins);
                  echo implode(', ', $coins);
                echo '</small></td>';
              echo '</tr>';
            }

            // print wallet distribution
            echo '<tr><th colspan="5" class="text-secondary">Wallets</th></tr>';
            foreach($wallets as $wallet) {
              echo '<tr>';
                echo '<td class="align-bottom text-secondary">'.$wallet->platform.'</td>';
                echo '<td class="align-bottom">'.$wallet->wallet.'</td>';
                echo '<td class="align-bottom text-secondary"><small>Σ<small></td>';
                echo '<td class="text-right align-bottom text-secondary">'.($totalValue > 0 ? number_format($wallet->value / $totalValue * 100, 1) : '-').' %</td>';
                echo '<td class="text-right text-bigger">'.number_format($wallet->value, 2).' '.$currencyLabel.'</td>';
                echo '<td class="align-bottom text-secondary"><small>';
                  $coins = array_keys($wallet->coins);
                  foreach($coins as $index=>$coin) {
                    if(false !== $assets[$coin]->symbol) {
                      $coins[$index] = strtoupper($assets[$coin]->symbol);
                    }
                  }
                  sort($coins);
                  echo implode(', ', $coins);
                echo '</small></td>';
              echo '</tr>';
            }

            // print single accounts
            echo '<tr><th colspan="5" class="text-secondary">Accounts</th></tr>';
            foreach($accounts as $account) {
              echo '<tr>';
                echo '<td class="align-bottom text-secondary">'.$account->platform.'</td>';
                echo '<td class="align-bottom text-secondary">'.$account->wallet.'</td>';
                echo '<td class="align-bottom">'.$account->account.'</td>';
                echo '<td class="text-right align-bottom text-secondary">'.($totalValue > 0 ? number_format($account->value / $totalValue * 100, 1) : '-').' %</td>';
                echo '<td class="text-right text-bigger">'.number_format($account->value, 2).' '.$currencyLabel.'</td>';
                echo '<td class="align-bottom">';
                  echo (false == $assets[$account->symbol]->symbol ? $account->symbol : $assets[$account->symbol]->image_thumb.' '.strtoupper($assets[$account->symbol]->symbol));
                  echo ' <span class="text-secondary">'.number_format_nice($account->amount, 8).'</span>';
                echo '</td>';
              echo '</tr>';
            }
          echo '</tbody>';
        echo '</table>';
      echo '</tr>';

      uasort($assets, function($a, $b){
        if ($a->value == $b->value) {
          return 0;
        }
        return ($a->value > $b->value) ? -1 : 1;
      });

      // prepare chart variables
      $chartValues = [];
      $chartLabels = [];
      foreach($assets as $symbol=>$asset) {
        $chartValues[$symbol] = $asset->value;
        $label = $symbol;
        if(false !== $asset->name) {
          $label = strtoupper($asset->symbol);
          if(strtoupper($asset->symbol) == $asset->name) {
            $label = $asset->name;
          }
        }
        $chartLabels[] = $label;
      }

      // print asset statistics
      $iteration = 0;
      foreach($assets as $symbol=>$asset) {
        $iteration++;
        $percentage = false;
        if($totalValue > 0) {
          $percentage = $asset->value / $totalValue * 100;
        }
        echo '<tr>';
          if(1 == $iteration) {
            echo '<td rowspan="'.sizeof($assets).'" style="position: relative; width: 42%;">';
              echo '<div style="position: absolute; left: 0; top: 0; width: 100%; height: 100%;">';
              if(sizeof($assets) > 0) : ?>
                <canvas id="pieChart" style="width: 100%; height: 95%; margin-top: 0.5em;"></canvas>
                <script>
                var borderColors = [];
                for(i = 0; i < <?php echo sizeof($assets); ?>; i++) {
                  borderColors[i] = '#191d21';
                }
                var ctx = document.getElementById('pieChart').getContext('2d');
                var pieChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: [<?php echo "'".implode("', '", $chartLabels)."'"; ?>],
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
          echo '<td class="text-right text-secondary align-middle">'.(false === $percentage ? '-' : round($percentage, 1)).' %</td>';
          echo '<td class="text-right text-bigger align-middle">'.number_format($asset->value, 2).' '.$currencyLabel.'</td>';
          echo '<td class="align-middle">';
            echo (false == $asset->symbol ? $symbol : $asset->image_thumb.' '.strtoupper($asset->symbol));
            echo ' <span class="text-secondary">'.number_format_nice($asset->amount, 8).'</span>';
            echo (false != $asset->name ? ' <small class="d-none d-lg-inline">'.$asset->name.'</small>' : '');
          echo '</td>';
        echo '</tr>';
      }
    echo '</tbody>';
  echo '</table>';

  echo '<div class="container mt-3">';
    echo '<h5 class="">Market data <small class="text-muted">'.sizeof($assets).' coins/tokens</small></h5>';
    echo '<div class="row" style="border-top: 1px solid #333; border-bottom: 1px solid #333;">';
      echo '<div class="col">';
        // print general price info
        echo '<table class="table table-hover table-sm table-borderless" id="marketData">';
          echo '<thead>';
            echo '<tr style="border-bottom: 1px solid #333; border-bottom: 1px solid #333;">';
              echo '<th colspan="2" title="Coin/Token symbol">Coin/Token</th>';
              echo '<th class="text-right" title="Current value of one coin/token">Value</th>';
              echo '<th class="text-right" title="Current value of one coin/token">Holdings</th>';
              echo '<th class="text-right" title="Price change percentage in the last 1 hour">1h</th>';
              echo '<th class="text-right" title="Price change percentage in the last 24 hours">24h</th>';
              echo '<th class="text-right" title="Price change percentage in the last 7 days">7d</th>';
              // echo '<th class="text-right" title="Price change percentage in the last 30 days">30d</th>';
              // echo '<th class="text-right" title="Price change percentage in the last 60 days">60d</th>';
              echo '<th class="text-right d-none d-lg-table-cell" title="Last all time high">ATH</th>';
              echo '<th class="text-right d-none d-lg-table-cell" title="All time high value">'.$currencyLabel.'</th>';
              echo '<th class="text-right d-none d-lg-table-cell" title="Current price in relation to all time high">%</th>';
              echo '<th class="text-center" title="Market cap rank">Rank</th>';
              echo '<th class="text-center" title="Supply in circulation">Circulating</th>';
              echo '<th class="text-right" title="Value per circulating coin/token if total supply was 100,000,000"><i class="fa fa-coins"></i></th>';
              echo '<th data-sorter="false"></th>';
            echo '</tr>';
          echo '<thead>';
          echo '<tbody>';
            foreach($assets as $symbol => $asset) {
              $price = null;
              if(isset($prices[$symbol]) && isset($prices[$symbol][strtolower($currency)])) {
                $price = $prices[$symbol][strtolower($currency)];
              }
              if(!$asset->id || !($coinInfo = $api->getCoin($asset->id))) {
                echo '<tr><td colspan="14">';
                  echo $symbol.' <small>market data unavailable</small>';
                echo '</td></tr>';
                continue;
              }

              echo '<tr>';
                echo '<td class="text-right">';
                  echo '<a href="https://www.coingecko.com/en/coins/'.$asset->id.'/'.strtolower($currency).'" target="_blank">';
                    echo '1 '.$asset->image_thumb.' '.strtoupper($asset->symbol);
                  echo '</a>';
                echo '</td>';
                echo '<td> = </td>';
                echo '<td class="text-right" data-text="'.(null !== $price ? number_format($price, 8) : '').'">'.(null !== $price ? number_format_nice($price, 8) : '').'&nbsp;'.$currencyLabel.'</td>';
                echo '<td class="text-right align-bottom" data-text="'.number_format($asset->value, 2).'"><small class="text-secondary">'.number_format($asset->value).'&nbsp;'.$currencyLabel.'</small></td>';
                $percRanges = [
                  'price_change_percentage_1h_in_currency',
                  'price_change_percentage_24h',
                  'price_change_percentage_7d',
                  // 'price_change_percentage_30d',
                  // 'price_change_percentage_60d',
                ];
                foreach($percRanges as $perc) {
                  echo '<td class="text-right align-bottom">';
                    if(preg_match('/_currency$/i', $perc)) {
                      $change = (float) $coinInfo->market_data->$perc->{strtolower($currency)};
                    }
                    else {
                      $change = (float) $coinInfo->market_data->$perc;
                    }
                    $changeClass = 'text-secondary';
                    if($change > 0) {
                      $changeClass = 'text-success';
                    }
                    else if($change < 0) {
                      $changeClass = 'text-danger';
                    }
                    echo '<small class="'.$changeClass.'">'.number_format($change, 1);
                    echo '&nbsp;%</small>';
                  echo '</td>';
                }

                // ATH time info
                $agoValue = '';
                $agoString = '';
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
                echo '<td data-text="'.$agoValue.'" class="text-right d-none d-lg-table-cell">';
                  if(!empty($agoString)) {
                    echo $agoString;
                  }
                echo '</td>';

                // ATH value
                $athValue = '';
                $athString = '';
                $athValue = $coinInfo->market_data->ath->{strtolower($currency)};
                $athString .= '<small>'.number_format_nice($coinInfo->market_data->ath->{strtolower($currency)}, 8);
                $athString .= '&nbsp;'.$currencyLabel.'</small>';
                echo '<td data-text="'.$athValue.'" class="text-right align-bottom d-none d-lg-table-cell">';
                  if(!empty($athString)) {
                    echo $athString;
                  }
                echo '</td>';

                // ATH percentage
                $athValue = '';
                $athString = '';
                $athValue = $coinInfo->market_data->ath_change_percentage->{strtolower($currency)};
                $athString = '<small class="text-info">'.number_format($coinInfo->market_data->ath_change_percentage->{strtolower($currency)}, 1).'&nbsp;%</small>';
                echo '<td data-text="'.$athValue.'" class="text-right align-bottom d-none d-lg-table-cell">';
                  if(!empty($athString)) {
                    echo $athString;
                  }
                echo '</td>';
                echo '<td class="text-right">';
                  if($rank = $coinInfo->market_data->market_cap_rank) {
                    $rankClass = 'text-secondary';
                    if($rank <= 10) {
                      $rankClass = 'text-success';
                    }
                    else if($rank <= 50) {
                      $rankClass = 'text-warning';
                    }
                    echo '<span class="'.$rankClass.'">'.$rank.'</span>';
                  }
                echo '</td>';
                echo '<td class="text-center align-bottom">';
                  $totalSupply = $coinInfo->market_data->total_supply;
                  $circulatingSupply = $coinInfo->market_data->circulating_supply;
                  if($totalSupply > 0 && $circulatingSupply > 0) {
                    echo '<small title="'.number_format($circulatingSupply).' circulating / '.number_format($totalSupply).' total">'.number_format($circulatingSupply/$totalSupply*100).'&nbsp;%</small>';
                  }
                  else if($circulatingSupply > 0) {
                    echo '<small title="'.number_format($circulatingSupply).' circulating">Σ</small>';
                  }
                echo '</td>';

                $colValue = '';
                $colString = '';
                if($circulatingSupply > 0 && $price > 0) {
                  $colValue = number_format_nice($price * $circulatingSupply / 100000000);
                  $colString .= '<small class="text-secondary">'.number_format_nice($price * $circulatingSupply / 100000000);
                  $colString .= '&nbsp;'.$currencyLabel.'</small>';
                }
                echo '<td class="text-right align-bottom" data-text="'.$colValue.'">';
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
                      echo '<p class="description"><span class="text-secondary">'.substr($coinInfo->description->en, 0, 300).'...</span> More info at <a href="https://www.coingecko.com/en/coins/'.$asset->id.'" target="_blank">coingecko.com/'.$asset->id.'</a></p>';
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
          theme: 'custom',
          sortList: [[10,0]]
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