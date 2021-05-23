-- MariaDB dump 10.19  Distrib 10.4.19-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: drdev
-- ------------------------------------------------------
-- Server version	10.4.19-MariaDB
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `SEEDPerms`
--

DROP TABLE IF EXISTS `SEEDPerms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `SEEDPerms` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `fk_SEEDPerms_Classes` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_group` int(11) DEFAULT NULL,
  `modes` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`_key`),
  KEY `fk_SEEDPerms_Classes` (`fk_SEEDPerms_Classes`),
  KEY `user_id` (`user_id`),
  KEY `user_group` (`user_group`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SEEDPerms`
--

LOCK TABLES `SEEDPerms` WRITE;
/*!40000 ALTER TABLE `SEEDPerms` DISABLE KEYS */;
INSERT INTO `SEEDPerms` VALUES (1,NULL,NULL,NULL,NULL,0,1,-1,NULL,'R'),(2,NULL,NULL,NULL,NULL,0,1,1,NULL,'RWAP');
/*!40000 ALTER TABLE `SEEDPerms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `SEEDPerms_Classes`
--

DROP TABLE IF EXISTS `SEEDPerms_Classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `SEEDPerms_Classes` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `application` varchar(200) NOT NULL DEFAULT '',
  `name` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`_key`),
  KEY `application` (`application`(20))
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SEEDPerms_Classes`
--

LOCK TABLES `SEEDPerms_Classes` WRITE;
/*!40000 ALTER TABLE `SEEDPerms_Classes` DISABLE KEYS */;
INSERT INTO `SEEDPerms_Classes` VALUES (1,NULL,NULL,NULL,NULL,0,'DocRep','development');
/*!40000 ALTER TABLE `SEEDPerms_Classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `SEEDSession`
--

DROP TABLE IF EXISTS `SEEDSession`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `SEEDSession` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `sess_idstr` varchar(200) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `realname` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `permsR` text DEFAULT NULL,
  `permsW` text DEFAULT NULL,
  `permsA` text DEFAULT NULL,
  `ts_expiry` int(11) DEFAULT NULL,
  PRIMARY KEY (`_key`),
  KEY `sess_idstr` (`sess_idstr`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SEEDSession`
--

LOCK TABLES `SEEDSession` WRITE;
/*!40000 ALTER TABLE `SEEDSession` DISABLE KEYS */;
INSERT INTO `SEEDSession` VALUES (1,'2021-05-23 16:07:27',0,'2021-05-23 16:07:27',0,0,'da741992a4bf19c7de091b5da2ec313f',1,'Developer','dev',' DocRepMgr ',' DocRepMgr ',' DocRepMgr ',1621807647),(2,'2021-05-23 16:12:07',0,'2021-05-23 16:12:07',0,0,'f95ee206642804669b004bc18af30b81',1,'Developer','dev',' DocRepMgr ',' DocRepMgr ',' DocRepMgr ',1621807927);
/*!40000 ALTER TABLE `SEEDSession` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `SEEDSession_Groups`
--

DROP TABLE IF EXISTS `SEEDSession_Groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `SEEDSession_Groups` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `groupname` varchar(200) DEFAULT NULL,
  `gid_inherited` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`_key`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SEEDSession_Groups`
--

LOCK TABLES `SEEDSession_Groups` WRITE;
/*!40000 ALTER TABLE `SEEDSession_Groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `SEEDSession_Groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `SEEDSession_GroupsMetadata`
--

DROP TABLE IF EXISTS `SEEDSession_GroupsMetadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `SEEDSession_GroupsMetadata` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `gid` int(11) NOT NULL,
  `k` varchar(200) NOT NULL DEFAULT '',
  `v` text NOT NULL,
  PRIMARY KEY (`_key`),
  KEY `gid` (`gid`),
  KEY `k` (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SEEDSession_GroupsMetadata`
--

LOCK TABLES `SEEDSession_GroupsMetadata` WRITE;
/*!40000 ALTER TABLE `SEEDSession_GroupsMetadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `SEEDSession_GroupsMetadata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `SEEDSession_Perms`
--

DROP TABLE IF EXISTS `SEEDSession_Perms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `SEEDSession_Perms` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `perm` varchar(200) DEFAULT NULL,
  `modes` varchar(10) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `gid` int(11) DEFAULT NULL,
  PRIMARY KEY (`_key`),
  KEY `uid` (`uid`),
  KEY `gid` (`gid`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SEEDSession_Perms`
--

LOCK TABLES `SEEDSession_Perms` WRITE;
/*!40000 ALTER TABLE `SEEDSession_Perms` DISABLE KEYS */;
INSERT INTO `SEEDSession_Perms` VALUES (1,NULL,NULL,NULL,NULL,0,'DocRepMgr','RWA',1,NULL);
/*!40000 ALTER TABLE `SEEDSession_Perms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `SEEDSession_Users`
--

DROP TABLE IF EXISTS `SEEDSession_Users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `SEEDSession_Users` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `realname` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `password` varchar(200) DEFAULT NULL,
  `ext_uid` int(11) DEFAULT NULL,
  `gid1` int(11) DEFAULT NULL,
  `eStatus` enum('PENDING','ACTIVE','INACTIVE') NOT NULL DEFAULT 'PENDING',
  `sExtra` text DEFAULT NULL,
  `sentmsd2012` int(11) NOT NULL DEFAULT 0,
  `sentmsd2013` int(11) NOT NULL DEFAULT 0,
  `dSentmsd` varchar(200) DEFAULT '',
  `lang` enum('E','F','B') NOT NULL DEFAULT 'E',
  PRIMARY KEY (`_key`),
  KEY `email` (`email`),
  KEY `ext_uid` (`ext_uid`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SEEDSession_Users`
--

LOCK TABLES `SEEDSession_Users` WRITE;
/*!40000 ALTER TABLE `SEEDSession_Users` DISABLE KEYS */;
INSERT INTO `SEEDSession_Users` VALUES (1,'2021-05-23 16:06:18',1,'2021-05-23 16:06:18',1,0,'Developer','dev','dev',0,0,'ACTIVE','',0,0,'','E');
/*!40000 ALTER TABLE `SEEDSession_Users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `SEEDSession_UsersMetadata`
--

DROP TABLE IF EXISTS `SEEDSession_UsersMetadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `SEEDSession_UsersMetadata` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `uid` int(11) NOT NULL,
  `k` varchar(200) NOT NULL DEFAULT '',
  `v` text NOT NULL,
  PRIMARY KEY (`_key`),
  KEY `uid` (`uid`),
  KEY `k` (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SEEDSession_UsersMetadata`
--

LOCK TABLES `SEEDSession_UsersMetadata` WRITE;
/*!40000 ALTER TABLE `SEEDSession_UsersMetadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `SEEDSession_UsersMetadata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `SEEDSession_UsersXGroups`
--

DROP TABLE IF EXISTS `SEEDSession_UsersXGroups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `SEEDSession_UsersXGroups` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `uid` int(11) DEFAULT NULL,
  `gid` int(11) DEFAULT NULL,
  PRIMARY KEY (`_key`),
  KEY `uid` (`uid`),
  KEY `gid` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SEEDSession_UsersXGroups`
--

LOCK TABLES `SEEDSession_UsersXGroups` WRITE;
/*!40000 ALTER TABLE `SEEDSession_UsersXGroups` DISABLE KEYS */;
/*!40000 ALTER TABLE `SEEDSession_UsersXGroups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `docrep2_data`
--

DROP TABLE IF EXISTS `docrep2_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `docrep2_data` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `fk_docrep2_docs` int(11) NOT NULL,
  `ver` int(11) NOT NULL DEFAULT 1,
  `src` enum('TEXT','FILE','SFILE','LINK') NOT NULL,
  `data_text` text DEFAULT NULL,
  `data_file_ext` varchar(20) DEFAULT NULL,
  `data_sfile_name` varchar(500) DEFAULT NULL,
  `data_link_doc` int(11) DEFAULT NULL,
  `title` varchar(200) DEFAULT '',
  `mimetype` varchar(100) DEFAULT '',
  `dataspec` varchar(200) DEFAULT '',
  `metadata` text DEFAULT NULL,
  PRIMARY KEY (`_key`),
  KEY `fk_docrep2_docs` (`fk_docrep2_docs`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `docrep2_data`
--

LOCK TABLES `docrep2_data` WRITE;
/*!40000 ALTER TABLE `docrep2_data` DISABLE KEYS */;
INSERT INTO `docrep2_data` VALUES (1,'2021-03-28 11:20:31',NULL,'2021-03-28 11:20:31',NULL,0,1,1,'TEXT','',NULL,NULL,NULL,'','','',NULL),(2,'2021-03-28 11:20:31',NULL,'2021-03-28 11:20:31',NULL,0,2,1,'TEXT','This is page A',NULL,NULL,NULL,'','','',NULL),(3,'2021-03-28 11:20:31',NULL,'2021-03-28 11:20:31',NULL,0,3,1,'TEXT','This is page B',NULL,NULL,NULL,'','','',NULL),(4,'2021-03-28 11:20:31',NULL,'2021-03-28 11:20:31',NULL,0,4,1,'TEXT','',NULL,NULL,NULL,'','','',NULL),(5,'2021-03-28 11:20:31',NULL,'2021-03-28 11:20:31',NULL,0,5,1,'TEXT','This is page C',NULL,NULL,NULL,'','','',NULL),(6,'2021-03-28 11:20:31',NULL,'2021-03-28 11:20:31',NULL,0,6,1,'TEXT','This is page D',NULL,NULL,NULL,'','','',NULL),(7,'2021-03-28 11:20:31',NULL,'2021-03-28 11:20:31',NULL,0,7,1,'TEXT','',NULL,NULL,NULL,'','','',NULL),(8,'2021-03-28 11:20:31',NULL,'2021-03-28 11:20:31',NULL,0,8,1,'TEXT','This is page E',NULL,NULL,NULL,'','','',NULL),(9,'2021-03-28 11:20:31',NULL,'2021-03-28 11:20:31',NULL,0,9,1,'TEXT','This is page F',NULL,NULL,NULL,'','','',NULL);
/*!40000 ALTER TABLE `docrep2_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `docrep2_docs`
--

DROP TABLE IF EXISTS `docrep2_docs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `docrep2_docs` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `name` varchar(200) NOT NULL DEFAULT '',
  `type` varchar(200) NOT NULL DEFAULT '',
  `docspec` varchar(200) DEFAULT '',
  `permclass` int(11) NOT NULL,
  `kData_top` int(11) NOT NULL DEFAULT 0,
  `kDoc_parent` int(11) NOT NULL DEFAULT 0,
  `siborder` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`_key`),
  KEY `name` (`name`(20)),
  KEY `kDoc_parent` (`kDoc_parent`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `docrep2_docs`
--

LOCK TABLES `docrep2_docs` WRITE;
/*!40000 ALTER TABLE `docrep2_docs` DISABLE KEYS */;
INSERT INTO `docrep2_docs` VALUES (1,'2021-03-28 15:24:55',NULL,'2021-03-28 15:24:55',NULL,0,'folder1','FOLDER','',1,1,0,1),(2,'2021-03-28 15:24:55',NULL,'2021-03-28 15:24:55',NULL,0,'folder1/pageA','DOC','',1,2,1,1),(3,'2021-03-28 15:24:55',NULL,'2021-03-28 15:24:55',NULL,0,'folder1/pageB','DOC','',1,3,1,2),(4,'2021-03-28 15:24:55',NULL,'2021-03-28 15:24:55',NULL,0,'folder2','FOLDER','',1,4,0,2),(5,'2021-03-28 15:24:55',NULL,'2021-03-28 15:24:55',NULL,0,'folder2/pageC','DOC','',1,5,4,1),(6,'2021-03-28 15:24:55',NULL,'2021-03-28 15:24:55',NULL,0,'folder2/pageD','DOC','',1,6,4,2),(7,'2021-03-28 15:24:55',NULL,'2021-03-28 15:24:55',NULL,0,'folder1/folder3','FOLDER','',1,7,1,3),(8,'2021-03-28 15:24:55',NULL,'2021-03-28 15:24:55',NULL,0,'folder1/folder3/pageE','DOC','',1,8,7,1),(9,'2021-03-28 15:24:55',NULL,'2021-03-28 15:24:55',NULL,0,'folder1/folder3/pageF','DOC','',1,9,7,2);
/*!40000 ALTER TABLE `docrep2_docs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `docrep2_docxdata`
--

DROP TABLE IF EXISTS `docrep2_docxdata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `docrep2_docxdata` (
  `_key` int(11) NOT NULL AUTO_INCREMENT,
  `_created` datetime DEFAULT NULL,
  `_created_by` int(11) DEFAULT NULL,
  `_updated` datetime DEFAULT NULL,
  `_updated_by` int(11) DEFAULT NULL,
  `_status` int(11) DEFAULT 0,
  `fk_docrep2_docs` int(11) NOT NULL,
  `fk_docrep2_data` int(11) NOT NULL,
  `flag` varchar(200) NOT NULL,
  PRIMARY KEY (`_key`),
  KEY `fk_docrep2_docs` (`fk_docrep2_docs`),
  KEY `fk_docrep2_data` (`fk_docrep2_data`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `docrep2_docxdata`
--

LOCK TABLES `docrep2_docxdata` WRITE;
/*!40000 ALTER TABLE `docrep2_docxdata` DISABLE KEYS */;
/*!40000 ALTER TABLE `docrep2_docxdata` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2021-05-23 16:13:18
