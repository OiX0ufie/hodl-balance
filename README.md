# hodl-balance

Keep track of your crypto holdings in any currency.
All information is stored AES encrypted as browser cookie.
All price information is fetched from Coingecko API.

## Table structure
CREATE TABLE `storage` (
  `key` varchar(255) NOT NULL,
  `data` text NOT NULL,
  `updated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ALTER TABLE `storage`
  ADD PRIMARY KEY (`key`,`updated`);
  
## Big thanks to those awesome projects

* Coingecko API https://www.coingecko.com/en/api
* jQuery https://jquery.com/
* Bootstrap https://getbootstrap.com/
* Font Awesome https://fontawesome.com/
* JavaScript Cookie https://github.com/js-cookie/js-cookie
* CryptoJS https://code.google.com/archive/p/crypto-js/
* CryptoJS AES PHP https://github.com/brainfoolong/cryptojs-aes-php
