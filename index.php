<?php
    
    require_once(__DIR__.'/config.php');
    require_once(__DIR__.'/api.coingecko.php');
    $api = new Api();
    $preferredCurrencies = [
        'fiat currencies' => ['USD', 'EUR', 'CNY', 'RUB'],
        'crypto currencies' => ['BTC', 'ETH']
    ];
    $supportedCurrencies = $api->getCurrencies();
    if(is_array($supportedCurrencies)) {
        sort($supportedCurrencies);
    }
    else {
        $supportedCurrencies = [];
    }

?><html>
    <head>
        <title>Hodl balance</title>
        <link rel="stylesheet" href="res/fontawesome/css/all.min.css">
        <link rel="stylesheet" href="res/fontawesome/css/brands.min.css">
        <link rel="stylesheet" href="res/bootstrap/css/bootstrap-dark.min.css">
        <script src="res/jquery.min.js"></script>
        <script src="res/js.cookie.min.js"></script>
        <script src="res/bootstrap/js/bootstrap.min.js"></script>
        <script src="res/cryptoJS/rollups/aes.js"></script>
        <script src="res/cryptoJS/cryptojs-aes-format.js"></script>
        <style>
            #exportWrapper,
            #importWrapper {
                position: fixed;
                top: 5em;
                left: 10%;
                width: 80%;
                height: 65%;
                padding: 3em;
                z-index: 900;
                background: #272D33;
                border-radius: 5px;
            }
            #exportWrapper{
                padding-bottom: 3.7em;
            }
            #exportWrapper textarea,
            #importWrapper textarea {
                width: 100%;
                height: 100%;
                border: 2px solid #222;
                background: rgba(0, 0, 0, 0.2);
                color: #fff;
                word-wrap:break-word;
                resize: none;
            }
            #balanceDisplay {
                max-width: 1000px;
                padding: 0 1vw;
                margin: 0 auto;
                overflow: hidden;
            }
            #balanceDisplay pre {
                font-size: 2.1vmin;
                color: #f2f2f2;
            }
        </style>
        <script>

            var addEntry = function(entryData) {
                var newEntry = $('#hodlForm tbody .entry-template').clone();
                newEntry.removeClass('entry-template d-none');
                newEntry.addClass('entry');
                // propagate data
                if("undefined" != typeof(entryData) && null != entryData) {
                    if(entryData.hasOwnProperty('platform')) {
                        $('input[name="platform"]', newEntry).val(entryData.platform);
                    }
                    if(entryData.hasOwnProperty('wallet')) {
                        $('input[name="wallet"]', newEntry).val(entryData.wallet);
                    }
                    if(entryData.hasOwnProperty('account')) {
                        $('input[name="account"]', newEntry).val(entryData.account);
                    }
                    if(entryData.hasOwnProperty('symbol')) {
                        $('input[name="symbol"]', newEntry).val(entryData.symbol);
                    }
                    if(entryData.hasOwnProperty('amount')) {
                        $('input[name="amount"]', newEntry).val(entryData.amount);
                    }
                }
                $('#hodlForm tbody').append(newEntry);
                if("undefined" == typeof(entryData)) {
                    updateButtons(true);
                }
                else {
                    updateButtons(false);
                }
            }

            var removeEntry = function(elem) {
                if(confirm('Remove this account?')) {
                    $(elem).parents('tr').remove();
                    updateButtons(true);
                }
            }

            var moveEntry = function (elem, direction) {
                var self = $(elem).parents('tr');
                if('up' == direction) {
                    $(self).insertBefore($(self).prev());
                }
                else if('down' == direction) {
                    $(self).insertAfter($(self).next());
                }
                updateButtons(true);
            }

            var updateButtons = function(highlightSaveButton) {
                if(undefined == highlightSaveButton) {
                    highlightSaveButton = false;
                }
                var entries = $('#hodlForm .entry').length;
                $('#hodlForm .entry').each(function(index) {
                    $('button', this).addClass('d-none');
                    // last entry
                    if(index == entries-1) {
                        $('button.addEntry', this).removeClass('d-none');
                        if(entries > 1) {
                            $('button.moveUp', this).removeClass('d-none');
                        }
                    }
                    // all other entries
                    else {
                        $('button.removeEntry', this).removeClass('d-none');
                        $('button.moveDown', this).removeClass('d-none');
                        if(index > 0) {
                            $('button.moveUp', this).removeClass('d-none');
                        }
                    }
                });
                if(highlightSaveButton) {
                    $('#saveButton').removeClass('btn-secondary');
                    $('#saveButton').addClass('btn-primary');
                    $('#cancelButton').removeClass('d-none');
                }
            }

            var resetForm = function() {
                loadCookieData(currentEncryptionKey);
                $('#saveButton').removeClass('btn-primary');
                $('#saveButton').addClass('btn-secondary');
                $('#cancelButton').addClass('d-none');
            }

            var hidePassword = function() {
                $('#hodlForm input[name="encryptionKey"]').attr('type', 'password');
                $('#hodlForm input[name="encryptionKey"]').parent().find('i').removeClass('fa-eye-slash').addClass('fa-eye');
            }

            var togglePassword = function(elem) {
                var type = $('#hodlForm input[name="encryptionKey"]').attr('type');
                if('password' == type) {
                    $('#hodlForm input[name="encryptionKey"]').attr('type', 'text');
                    $('i', elem).removeClass('fa-eye').addClass('fa-eye-slash');
                }
                else {
                    $('#hodlForm input[name="encryptionKey"]').attr('type', 'password');
                    $('i', elem).removeClass('fa-eye-slash').addClass('fa-eye');
                }
            }

            var saveFormData = function() {
                var encryptionKey = $('#hodlForm input[name="encryptionKey"]').val();
                var data = {
                    'currency': $('#hodlForm select[name="currency"]').val(),
                    'entries': []
                }
                $('#hodlForm tbody .entry').each(function() {
                    var symbol = $('input[name="symbol"]', this).val();
                    if(symbol.length > 0) {
                        data.entries.push({
                            'platform': $('input[name="platform"]', this).val(),
                            'wallet': $('input[name="wallet"]', this).val(),
                            'account': $('input[name="account"]', this).val(),
                            'symbol': symbol,
                            'amount': $('input[name="amount"]', this).val()
                        });
                    }
                });
                if(data.entries.length > 0) {
                    data.lastUpdate = Date.now();
                    var encodedData = CryptoJS.AES.encrypt(JSON.stringify(data), encryptionKey, { format: CryptoJSAesJson }).toString();
                    Cookies.set('hodl', encodedData, { expires: 365 });
                    currentEncryptionKey = encryptionKey;
                    showMessage('browser cookie stored', 'Success', 'check');
                }
                resetForm();
            }

            var deleteData = function() {
                if(confirm('Delete all data?')) {
                    Cookies.remove('hodl');
                    resetForm();
                    showMessage('browser cookie removed', 'Success', 'check');
                }
            }

            var exportData = function() {
                var data = Cookies.get('hodl');
                $('#exportWrapper textarea').val(data);
                $('#exportWrapper a.downloader').attr('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(data));
                $('#exportWrapper').removeClass('d-none');
            }

            var importData = function() {
                var data = $('#importWrapper textarea').val();
                if(data.length > 0) {
                    $('#importWrapper textarea').val('');
                    Cookies.set('hodl', data, { expires: 365 });
                    showMessage('data imported', 'Success', 'check');
                    resetForm();
                }
                $('#importWrapper').addClass('d-none');
            }

            var loadCookieData = function(encryptionKey) {
                if("undefined" == typeof(encryptionKey)) {
                    encryptionKey = '';
                }
                currentEncryptionKey = encryptionKey;
                var hodl = Cookies.get('hodl');
                if("undefined" != typeof(hodl)) {
                    try {
                        // try decrypting with empty key
                        var decryptedData = CryptoJS.AES.decrypt(hodl, encryptionKey, { format: CryptoJSAesJson }).toString(CryptoJS.enc.Utf8);
                        var data = JSON.parse(decryptedData);
                        propagateForm(data, encryptionKey);
                        return true;
                    } catch(e) {
                        if(encryptionKey = prompt('Please enter encryption key')) {
                            return loadCookieData(encryptionKey);
                        }
                        else {
                            showMessage('Refresh page to try again', 'Encryption failed', 'exclamation-triangle');
                        }
                    };
                }
                propagateForm(null, encryptionKey);
                return false;
            }

            var propagateForm = function(data, encryptionKey) {
                $('.balanceActionContent').addClass('d-none');
                $('#hodlForm input[name="encryptionKey"]').val(currentEncryptionKey);
                $('#hodlForm tbody .entry').remove();
                if(null == data) {
                    addEntry(null);
                }
                else {
                    if(data.hasOwnProperty('currency')) {
                        $('#hodlForm select[name="currency"]').val(data.currency);
                    }
                    if(data.hasOwnProperty('entries')) {
                        if(data.entries instanceof Array && data.entries.length > 0) {
                            for(i = 0; i < data.entries.length; i++) {
                                addEntry(data.entries[i]);
                            }
                            $('.balanceActionContent').removeClass('d-none');
                            configOk = true;
                        }
                    }
                }
            }

            var showMessage = function(message, title, icon) {
                if("undefined" == typeof(title)) {
                    title = 'System notification';
                }
                $('#toast .toast-header strong').html(title);
                if("undefined" == typeof(icon)) {
                    icon = 'info';
                }
                $('#toast .toast-header i').removeClass();
                $('#toast .toast-header i').addClass('fa fa-' + icon);
                $('#toast .toast-body').html(message);
                $('#toast').toast('show');
            }

            var reloadBalance = function() {
                $('#reloadButton i').addClass('fa-spin');
                var ajaxUrl = 'balance.php?key=' + currentEncryptionKey;
                $.ajax({
                    url: ajaxUrl,
                    cache: false
                }).done(function(data) {
                    $('#balanceDisplay').html('<pre>' + data + '</pre>');
                }).always(function() {
                    $('#reloadButton i').removeClass('fa-spin');
                });
            }

            var switchContent = function(action) {
                if('balance' == action) {
                    reloadBalance();
                    $('.actionWrapper').addClass('d-none');
                    $('#balanceWrapper').removeClass('d-none');
                }
                else if('init' == action) {
                    if(configOk) {
                        resetForm();
                    }
                    $('.actionWrapper').addClass('d-none');
                    $('#initWrapper').removeClass('d-none');
                }
            }

            var setAction = function(action) {
                hidePassword();
                if('undefined' == typeof(action) || !(['balance', 'init'].includes(action))) {
                    if(configOk) {
                        switchContent('balance');
                    }
                    else {
                        switchContent('init');
                    }
                }
                else {
                    window.location.href = '#' + action;
                    switchContent(action);
                }
            }

            var currentEncryptionKey = '';
            var configOk = false;
            $(document).ready(function() {
                loadCookieData();
                setAction(window.location.hash.substr(1));
            });
        </script>
    </head>
    <bdoy data-theme="dark">
        <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2000" style="position: fixed; width: 35vmin; top: 2em; left: calc(50% - 17vmin); z-index: 9999;">
            <div class="toast-header">
                <i style="margin-right: 0.5em;"></i>
                <strong class="mr-auto"></strong>
                <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="toast-body"></div>
        </div>
        <div class="container-xl">
        <div id="exportWrapper" class="d-none">
                <div style="position: absolute; right: 1em; top: 1em;">
                    <a href="#" onclick="$('#exportWrapper').addClass('d-none'); return false;"><i class="fa fa-times" title="close export"></i></a>
                </div>
                <div style="position: absolute; right: 1em; bottom: 1em;">
                    <a href="#" onclick="$('#exportWrapper textarea').select(); document.execCommand('copy'); showMessage('data copied to clipboard', 'Success', 'check'); return false;"><i class="fa fa-copy" title="copy to clipboard"></i></a>
                    <a href="#" class="ml-1 downloader" target="_blank" download="hodl-data.json"><i class="fa fa-download" title="download file"></i></a>
                </div>
                <textarea></textarea>
            </div>
            <div id="importWrapper" class="text-center d-none">
                <div style="position: absolute; right: 1em; top: 1em;">
                    <a href="#" onclick="$('#importWrapper').addClass('d-none'); return false;"><i class="fa fa-times" title="close import"></i></a>
                </div>
                <div style="position: absolute; right: 1em; bottom: 1em;">
                    <a href="#" onclick="importData(); return false;"><i class="fa fa-save" title="import data"></i></a>
                </div>
                <textarea placeholder="paste your exported json data here and click the save button"></textarea>
            </div>
            <div id="initWrapper" class="actionWrapper d-none">
                <div class="row mt-4 mb-2">
                    <div class="col">
                        <div class="float-right" style="display: inline-block; margin-top: 0.42em;">
                            <h2 style="display: inline-block; font-weight: 300; font-size: 1.5rem; position: relative; top: 0.2em;">to the moon <i class="fa fa-rocket"></i></h2>
                            <button type="button" class="balanceActionContent btn btn-primary ml-4 d-none"onclick="setAction('balance'); return false;" title="show balance"><i class="fa fa-coins"></i></button>
                        </div>
                        <h1 style="display: inline-block;">
                            <i class="fa fa-coins"></i> Hodl
                            <?php if($_CONFIG['homeUrl']) : ?>
                                <small><a href="<?php echo $_CONFIG['homeUrl']; ?>" class="ml-4"><i class="fa fa-home"></i></a></small>
                            <?php endif; ?>
                        </h1>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <form id="hodlForm" action="#" onsubmit="return false;">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th class="align-top">
                                            <i class="fa fa-desktop"></i>&nbsp;Platform
                                            <small class="form-text text-muted">software or hardware provider name</small>
                                        </th>
                                        <th class="align-top">
                                            <i class="fa fa-wallet"></i>&nbsp;Wallet
                                            <small class="form-text text-muted">wallet name or label</small>
                                        </th>
                                        <th class="align-top">
                                            <i class="fa fa-tag"></i>&nbsp;Account
                                            <small class="form-text text-muted">account id, label or name</small>
                                        </th>
                                        <th class="align-top" style="width: 10%;">
                                            <i class="fab fa-btc"></i>&nbsp;Symbol
                                            <small class="form-text text-muted">BTC, ETH, ...</small>
                                        </th>
                                        <th class="align-top">
                                            0.00&nbsp;Amount
                                            <small class="form-text text-muted">amount of crypto currency</small>
                                        </th>
                                        <th class="align-top text-right">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-secondary" onclick="$('#importWrapper').removeClass('d-none'); return false;" title="import data"><i class="fa fa-upload"></i></button>
                                                <button type="button" class="btn btn-secondary" onclick="deleteData();return false;" title="delete all data"><i class="fa fa-trash"></i></button>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="d-none entry-template">
                                        <td><input class="form-control" type="text" name="platform" oninput="updateButtons(true);"></td>
                                        <td><input class="form-control" type="text" name="wallet" oninput="updateButtons(true);"></td>
                                        <td><input class="form-control" type="text" name="account" oninput="updateButtons(true);"></td>
                                        <td><input class="form-control text-center" type="text" name="symbol" placeholder="BTC" oninput="updateButtons(true);"></td>
                                        <td><input class="form-control" type="text" name="amount" placeholder="0.00" oninput="updateButtons(true);"></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-default removeEntry d-none" onclick="removeEntry(this); return false;" title="remove account"><i class="fa fa-trash"></i></button>
                                                <button type="button" class="btn btn-default addEntry d-none" onclick="addEntry(); return false;"><i class="fa fa-plus" title="add account"></i></button>
                                                <button type="button" class="btn btn-default moveUp d-none" onclick="moveEntry(this, 'up'); return false;" title="move up"><i class="fa fa-chevron-up"></i></button>
                                                <button type="button" class="btn btn-default moveDown d-none" onclick="moveEntry(this, 'down'); return false;" title="move down"><i class="fa fa-chevron-down"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2">
                                            <label>
                                                <p>
                                                    <i class="fa fa-key"></i> Encryption key <small>(optional)</small>
                                                    <small class="form-text text-muted">Encrypt cookie data with custom passphrase.</small>
                                                </p>
                                                <div class="input-group">
                                                    <input class="form-control" type="password" name="encryptionKey" oninput="updateButtons(true);">
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-default" onclick="togglePassword(this); return false;" title="show/hide key"><i class="fa fa-eye"></i></button>
                                                    </div>
                                                </div>
                                            </label>
                                        </td>
                                        <td colspan="2">
                                            <label>
                                                <p>
                                                    <i class="fa fa-dollar-sign"></i> Conversion currency
                                                    <small class="form-text text-muted">Converting all crypto values.</small>
                                                </p>
                                                <div class="input-group w-50">
                                                    <select class="form-control" name="currency" oninput="updateButtons(true);">
                                                        <?php
                                                            foreach($preferredCurrencies as $group=>$currencies) {
                                                                echo '<optgroup label="'.$group.'">';
                                                                    foreach($currencies as $currency) {
                                                                        echo '<option>'.$currency.'</option>';
                                                                    }
                                                                echo '</optgroup>';
                                                            }
                                                            echo '<optgroup label="all currencies">';
                                                            foreach($supportedCurrencies as $currency) {
                                                                if(!in_array($currency, $preferredCurrencies)) {
                                                                    echo '<option>'.$currency.'</option>';
                                                                }
                                                            }
                                                            echo '</optgroup>';
                                                        ?>
                                                    </select>
                                                </div>
                                            </label>
                                        </td>
                                        <td colspan="2" class="text-right align-bottom">
                                            <button type="submit" id="saveButton" class="btn btn-secondary" onclick="saveFormData(); return false;" title="save form data">save data <i class="fa fa-save"></i></button>
                                            <button type="reset" id="cancelButton" class="btn btn-secondary d-none" onclick="resetForm(); return false;" title="reset form data">cancel <i class="fa fa-ban"></i></button>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </form>
                    </div>
                </div>

                <div id="showBalance" class="balanceActionContent jumbotron jumbotron-fluid text-center d-none">
                    <h1 class="display-4">All set.</h1>
                    <p class="lead">Forward and upwards!</p>
                    <hr class="my-4">
                    <p class="lead text-center">
                        <a class="btn btn-primary btn-lg" href="?#balance" onclick="setAction('balance'); return false;" role="button">show balance <i class="fa fa-coins"></i></a>
                        <small class="ml-5">configuration</small>
                        <a class="btn btn-secondary btn-sm" href="?" onclick="exportData(); return false;" role="button">export <i class="fa fa-download"></i></a>
                    </p>
                </div>
            </div>
            <div id="balanceWrapper" class="actionWrapper d-none">
                <div class="container mt-3 mb-4">
                    <div class="float-right">
                        <a href="?#init" onclick="setAction('init'); return false;"><i class="fa fa-2x fa-cog"></i></a>
                    </div>
                    <div>
                        <a id="reloadButton" href="?" onclick="reloadBalance(); return false;"><i class="fa fa-2x fa-sync"></i></a>
                        <?php if($_CONFIG['homeUrl']) : ?>
                            <a href="<?php echo $_CONFIG['homeUrl']; ?>"><i class="fa fa-2x fa-home ml-4"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div id="balanceDisplay"></div>
                <div class="mb-3 text-center">
                    <small>Data provided by <a href="https://www.coingecko.com/en/api" target="_blank">Coingecko API</a></small>
                </div>
            </div>
        </div>
    </body>
</html>