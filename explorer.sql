# ************************************************************
# Sequel Pro SQL dump
# Version 4541
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: 127.0.0.1 (MySQL 5.5.5-10.2.13-MariaDB)
# Datenbank: explorer.emercoin.com
# Erstellt am: 2018-03-19 07:53:48 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Export von Tabelle address
# ------------------------------------------------------------

DROP TABLE IF EXISTS `address`;

CREATE TABLE `address` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `address` varchar(50) DEFAULT NULL,
  `balance` double DEFAULT NULL,
  `account` varchar(50) DEFAULT NULL,
  `last_sent` int(10) unsigned DEFAULT NULL,
  `last_received` int(10) unsigned DEFAULT NULL,
  `count_sent` int(10) unsigned DEFAULT NULL,
  `count_received` int(10) unsigned DEFAULT NULL,
  `total_sent` double DEFAULT NULL,
  `total_received` double DEFAULT NULL,
  `status` char(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;



# Export von Tabelle blocks
# ------------------------------------------------------------

DROP TABLE IF EXISTS `blocks`;

CREATE TABLE `blocks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(65) NOT NULL,
  `size` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `version` int(10) unsigned DEFAULT NULL,
  `merkleroot` varchar(65) DEFAULT NULL,
  `time` int(10) unsigned DEFAULT NULL,
  `nonce` varchar(50) DEFAULT NULL,
  `bits` varchar(50) DEFAULT NULL,
  `difficulty` varchar(50) DEFAULT NULL,
  `mint` double DEFAULT NULL,
  `previousblockhash` varchar(65) DEFAULT NULL,
  `flags` varchar(50) DEFAULT NULL,
  `proofhash` varchar(65) DEFAULT NULL,
  `entropybit` bit(1) DEFAULT NULL,
  `modifier` varchar(50) DEFAULT NULL,
  `modifierchecksum` varchar(50) DEFAULT NULL,
  `numtx` int(10) unsigned DEFAULT NULL,
  `numvin` int(10) unsigned DEFAULT NULL,
  `numvout` int(10) unsigned DEFAULT NULL,
  `valuein` double DEFAULT NULL,
  `valueout` double DEFAULT NULL,
  `fee` double DEFAULT NULL,
  `total_coins` double DEFAULT NULL,
  `coindaysdestroyed` double DEFAULT NULL,
  `avgcoindaysdestroyed` double DEFAULT NULL,
  `total_coindays` double DEFAULT NULL,
  `total_avgcoindays` double DEFAULT NULL,
  `total_addresses_used` int(10) unsigned DEFAULT NULL,
  `total_addresses_unused` int(10) unsigned DEFAULT NULL,
  `status` char(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;



# Export von Tabelle nvs
# ------------------------------------------------------------

DROP TABLE IF EXISTS `nvs`;

CREATE TABLE `nvs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(1000) DEFAULT NULL,
  `value` varchar(25000) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `isbase64` char(1) DEFAULT NULL,
  `registered_at` int(10) unsigned DEFAULT NULL,
  `expires_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;



# Export von Tabelle transactions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `transactions`;

CREATE TABLE `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `blockid` int(10) unsigned NOT NULL,
  `txid` varchar(65) NOT NULL,
  `time` int(10) unsigned DEFAULT NULL,
  `numvin` int(10) unsigned DEFAULT NULL,
  `numvout` int(10) unsigned DEFAULT NULL,
  `valuein` double DEFAULT NULL,
  `valueout` double DEFAULT NULL,
  `fee` float DEFAULT NULL,
  `coindaysdestroyed` double DEFAULT NULL,
  `avgcoindaysdestroyed` double DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;



# Export von Tabelle vin
# ------------------------------------------------------------

DROP TABLE IF EXISTS `vin`;

CREATE TABLE `vin` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `blockid` int(10) unsigned NOT NULL,
  `parenttxid` int(10) unsigned NOT NULL,
  `output_txid` varchar(65) NOT NULL,
  `coinbase` varchar(65) DEFAULT NULL,
  `vout` int(10) unsigned DEFAULT NULL,
  `asm` varchar(25000) DEFAULT NULL,
  `hex` varchar(25000) DEFAULT NULL,
  `sequence` varchar(50) DEFAULT NULL,
  `address` varchar(50) DEFAULT NULL,
  `value` double DEFAULT NULL,
  `coindaysdestroyed` double DEFAULT NULL,
  `avgcoindaysdestroyed` double DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;



# Export von Tabelle vout
# ------------------------------------------------------------

DROP TABLE IF EXISTS `vout`;

CREATE TABLE `vout` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `blockid` int(10) unsigned NOT NULL,
  `parenttxid` int(10) unsigned NOT NULL,
  `value` double DEFAULT NULL,
  `n` int(10) unsigned DEFAULT NULL,
  `asm` varchar(25000) DEFAULT NULL,
  `hex` varchar(25000) DEFAULT NULL,
  `reqsigs` int(10) unsigned DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `address` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;

Create INDEX address on address (address);
Create INDEX account on address (account);
Create INDEX balance on address (balance);
Create INDEX hash on blocks(hash);
Create INDEX height on blocks(height);
Create INDEX time on blocks(time);
Create INDEX flags on blocks(flags);
Create INDEX name on nvs (name);
Create INDEX txid on transactions(txid);
Create INDEX blockid on transactions(blockid);
Create INDEX time on transactions(time);
Create INDEX fee on transactions(fee);
Create INDEX address on  vin(address);
Create INDEX output_txid on  vin(output_txid);
Create INDEX coinbase on  vin(coinbase);
Create INDEX parenttxid on  vin(parenttxid);
Create INDEX blockid on  vin(blockid);
Create INDEX parenttxid on  vout(parenttxid);
Create INDEX blockid on  vout(blockid);
Create INDEX address on  vout(address);

/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
