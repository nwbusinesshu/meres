/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.13-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: quarma360_app
-- ------------------------------------------------------
-- Server version	10.11.13-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `assessment`
--

DROP TABLE IF EXISTS `assessment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `started_at` datetime NOT NULL,
  `due_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `org_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`org_snapshot`)),
  `org_snapshot_version` varchar(16) NOT NULL DEFAULT 'v1',
  `normal_level_up` smallint(6) DEFAULT NULL,
  `normal_level_down` smallint(6) DEFAULT NULL,
  `monthly_level_down` smallint(6) DEFAULT NULL,
  `suggested_decision` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`suggested_decision`)),
  `threshold_method` enum('fixed','hybrid','dynamic','suggested') NOT NULL DEFAULT 'fixed',
  PRIMARY KEY (`id`),
  KEY `idx_assessment_org_started` (`organization_id`,`started_at`),
  CONSTRAINT `fk_assessment_org_a1` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment`
--

LOCK TABLES `assessment` WRITE;
/*!40000 ALTER TABLE `assessment` DISABLE KEYS */;
INSERT INTO `assessment` VALUES
(1,1,'2022-09-25 19:48:08','2022-10-02 00:00:00','2022-10-10 10:50:37',NULL,'v1',85,70,70,NULL,'fixed'),
(2,1,'2022-12-05 16:38:27','2022-12-18 00:00:00','2022-12-22 12:30:46',NULL,'v1',85,70,70,NULL,'fixed'),
(3,1,'2023-03-20 13:37:44','2023-04-02 00:00:00','2023-04-03 10:00:22',NULL,'v1',85,70,70,NULL,'fixed'),
(4,1,'2023-06-20 17:47:59','2023-07-03 00:00:00','2023-07-05 07:51:20',NULL,'v1',85,70,70,NULL,'fixed'),
(5,1,'2023-09-18 09:33:34','2023-10-01 00:00:00','2023-10-03 15:08:20',NULL,'v1',85,70,70,NULL,'fixed'),
(6,1,'2023-12-13 17:33:47','2023-12-23 00:00:00','2024-01-04 10:21:42',NULL,'v1',85,70,70,NULL,'fixed'),
(7,1,'2024-03-19 11:29:00','2024-04-02 00:00:00','2024-04-03 07:42:26',NULL,'v1',85,70,70,NULL,'fixed'),
(8,1,'2025-08-29 12:33:23','2025-09-12 00:00:00','2025-08-29 16:40:54',NULL,'v1',85,70,70,NULL,'fixed'),
(9,1,'2025-08-31 09:20:43','2025-09-07 00:00:00','2025-09-01 23:31:16',NULL,'v1',85,70,70,NULL,'fixed'),
(13,1,'2025-09-03 15:01:14','2025-09-10 00:00:00','2025-09-10 16:41:57',NULL,'v1',79,64,70,'[{\"created_at\":\"2025-09-10T16:41:57+02:00\",\"model\":\"gpt-4.1-mini\",\"request\":{\"system\":\"You are a deterministic thresholds engine for a performance review cycle.\\r\\n\\r\\nINPUT: A strict JSON payload with:\\r\\n- current team scores (0..100),\\r\\n- optional per-user components (self\\/colleagues\\/managers\\/ceo),\\r\\n- ORGANIZATION-LEVEL telemetry summary (reliability of the whole measurement),\\r\\n- team statistics (mean\\/median\\/stdev\\/percentiles, histogram),\\r\\n- a short history of previous cycles with basic stats and thresholds,\\r\\n- and policy caps and constraints.\\r\\n\\r\\nTASK:\\r\\n1) Propose integer thresholds:\\r\\n   - normal_level_up (promotion)\\r\\n   - normal_level_down (demotion)\\r\\n2) Respect policy:\\r\\n   - Keep promotion rate around policy.target_promo_rate_max; demotion around policy.target_demotion_rate_max.\\r\\n   - If policy.never_below_abs_min_for_promo is a number, do not set \'normal_level_up\' below that value.\\r\\n   - If policy.no_forced_demotion_if_high_cohesion is true and team cohesion is high (e.g., low stdev\\/CV with high mean), it\'s acceptable to reduce demotions (raise \'down\').\\r\\n3) Use history to avoid unjustified drastic oscillations vs. previous cycles.\\r\\n4) Treat telemetry as reliability hints at the organization level; DO NOT overfit to outliers.\\r\\n5) Use this exact decision rule: promote if score >= normal_level_up; demote if score < normal_level_down; otherwise stay.\\r\\n6) Output STRICT JSON only:\\r\\n\\r\\n{\\r\\n  \\\"thresholds\\\": {\\r\\n    \\\"normal_level_up\\\": <int>,\\r\\n    \\\"normal_level_down\\\": <int>,\\r\\n    \\\"rationale\\\": \\\"<short>\\\"\\r\\n  },\\r\\n  \\\"decisions\\\": [\\r\\n    { \\\"user_id\\\": <int>, \\\"decision\\\": \\\"promote|stay|demote\\\", \\\"why\\\": \\\"<short>\\\" }\\r\\n  ],\\r\\n  \\\"summary_hu\\\": \\\"<Write a short (200-300 character) paragraph strictly in HUNGARIAN about your decision to let them know why those thresholds were selected. Keep it simple, pragmatic and user friendly. Do not leave room for any questions, be clear and sure. You want them to accept your thresholds.>\\\" }\\r\\n7) Your thresholds MUST satisfy:\\r\\n- promotions_count \\/ N <= policy.target_promo_rate_max\\r\\n- demotions_count  \\/ N <= policy.target_demotion_rate_max\\r\\nIf necessary, increase \'normal_level_up\' (even above the policy minimum) and\\/or increase \'normal_level_down\' to respect these caps.\\r\\nDefine high cohesion strictly as (stdev <= 8 and mean >= 80) or (CV <= 0.10). Otherwise, do not treat the team as high cohesion.\\r\\nReturn also:\\r\\n\\\"rates\\\": { \\\"promotion_rate\\\": <float>, \\\"promotion_count\\\": <int>, \\\"demotion_rate\\\": <float>, \\\"demotion_count\\\": <int>, \\\"n\\\": <int> }.\\r\\n}\",\"user\":\"PAYLOAD_JSON:\\n{\\\"meta\\\":{\\\"assessment_id\\\":13,\\\"org_id\\\":1,\\\"now\\\":\\\"2025-09-10T16:41:50+02:00\\\",\\\"method\\\":\\\"suggested\\\"},\\\"stats\\\":{\\\"count\\\":5,\\\"avg\\\":61.39999999999999857891452847979962825775146484375,\\\"median\\\":77,\\\"p10\\\":25.60000000000000142108547152020037174224853515625,\\\"p25\\\":64,\\\"p75\\\":79,\\\"p90\\\":83.7999999999999971578290569595992565155029296875,\\\"stdev\\\":35.2999999999999971578290569595992565155029296875,\\\"min\\\":0,\\\"max\\\":87,\\\"histogram\\\":[{\\\"from\\\":0,\\\"to\\\":9,\\\"count\\\":1},{\\\"from\\\":10,\\\"to\\\":19,\\\"count\\\":0},{\\\"from\\\":20,\\\"to\\\":29,\\\"count\\\":0},{\\\"from\\\":30,\\\"to\\\":39,\\\"count\\\":0},{\\\"from\\\":40,\\\"to\\\":49,\\\"count\\\":0},{\\\"from\\\":50,\\\"to\\\":59,\\\"count\\\":0},{\\\"from\\\":60,\\\"to\\\":69,\\\"count\\\":1},{\\\"from\\\":70,\\\"to\\\":79,\\\"count\\\":2},{\\\"from\\\":80,\\\"to\\\":89,\\\"count\\\":1},{\\\"from\\\":90,\\\"to\\\":100,\\\"count\\\":0}]},\\\"scores\\\":[0,64,77,79,87],\\\"users\\\":[{\\\"user_id\\\":43,\\\"total\\\":79,\\\"self\\\":76,\\\"ceo\\\":86,\\\"colleague\\\":86,\\\"manager\\\":71},{\\\"user_id\\\":55,\\\"total\\\":87,\\\"self\\\":100,\\\"ceo\\\":100,\\\"colleague\\\":78,\\\"manager\\\":78},{\\\"user_id\\\":56,\\\"total\\\":64,\\\"self\\\":50,\\\"ceo\\\":50,\\\"colleague\\\":79,\\\"manager\\\":69},{\\\"user_id\\\":57,\\\"total\\\":0,\\\"self\\\":0,\\\"ceo\\\":0,\\\"colleague\\\":0,\\\"manager\\\":0},{\\\"user_id\\\":58,\\\"total\\\":77,\\\"self\\\":78,\\\"ceo\\\":78,\\\"colleague\\\":78,\\\"manager\\\":73}],\\\"telemetry_org\\\":{\\\"n_records\\\":8,\\\"trust_score\\\":{\\\"mean\\\":8.5,\\\"median\\\":8.5,\\\"p10\\\":7,\\\"p25\\\":7.75,\\\"p75\\\":9.25,\\\"p90\\\":10,\\\"stdev\\\":1.1999999999999999555910790149937383830547332763671875},\\\"trust_index\\\":{\\\"mean\\\":42.5},\\\"flags\\\":{\\\"too_fast\\\":8,\\\"one_click_fast_read\\\":8,\\\"suspicious_pattern\\\":1,\\\"fast_read\\\":1},\\\"flags_rate\\\":{\\\"too_fast\\\":1,\\\"one_click_fast_read\\\":1,\\\"suspicious_pattern\\\":0.125,\\\"fast_read\\\":0.125},\\\"device_types\\\":{\\\"desktop\\\":8},\\\"suspicious_rate\\\":1},\\\"policy\\\":{\\\"target_promo_rate_max\\\":0.299999999999999988897769753748434595763683319091796875,\\\"target_demotion_rate_max\\\":0.299999999999999988897769753748434595763683319091796875,\\\"never_below_abs_min_for_promo\\\":70,\\\"no_forced_demotion_if_high_cohesion\\\":1},\\\"history\\\":[{\\\"assessment_id\\\":9,\\\"closed_at\\\":\\\"2025-09-01 23:31:16\\\",\\\"method\\\":\\\"fixed\\\",\\\"thresholds\\\":{\\\"up\\\":85,\\\"down\\\":70},\\\"stats\\\":{\\\"count\\\":5,\\\"avg\\\":61.7999999999999971578290569595992565155029296875,\\\"median\\\":78,\\\"p10\\\":24.39999999999999857891452847979962825775146484375,\\\"p25\\\":61,\\\"p75\\\":83,\\\"p90\\\":85.400000000000005684341886080801486968994140625,\\\"stdev\\\":35.93999999999999772626324556767940521240234375,\\\"min\\\":0,\\\"max\\\":87,\\\"histogram\\\":[{\\\"from\\\":0,\\\"to\\\":9,\\\"count\\\":1},{\\\"from\\\":10,\\\"to\\\":19,\\\"count\\\":0},{\\\"from\\\":20,\\\"to\\\":29,\\\"count\\\":0},{\\\"from\\\":30,\\\"to\\\":39,\\\"count\\\":0},{\\\"from\\\":40,\\\"to\\\":49,\\\"count\\\":0},{\\\"from\\\":50,\\\"to\\\":59,\\\"count\\\":0},{\\\"from\\\":60,\\\"to\\\":69,\\\"count\\\":1},{\\\"from\\\":70,\\\"to\\\":79,\\\"count\\\":1},{\\\"from\\\":80,\\\"to\\\":89,\\\"count\\\":2},{\\\"from\\\":90,\\\"to\\\":100,\\\"count\\\":0}]}},{\\\"assessment_id\\\":8,\\\"closed_at\\\":\\\"2025-08-29 16:40:54\\\",\\\"method\\\":\\\"fixed\\\",\\\"thresholds\\\":{\\\"up\\\":85,\\\"down\\\":70},\\\"stats\\\":{\\\"count\\\":5,\\\"avg\\\":59.7999999999999971578290569595992565155029296875,\\\"median\\\":71,\\\"p10\\\":22.39999999999999857891452847979962825775146484375,\\\"p25\\\":56,\\\"p75\\\":80,\\\"p90\\\":87.2000000000000028421709430404007434844970703125,\\\"stdev\\\":35.9200000000000017053025658242404460906982421875,\\\"min\\\":0,\\\"max\\\":92,\\\"histogram\\\":[{\\\"from\\\":0,\\\"to\\\":9,\\\"count\\\":1},{\\\"from\\\":10,\\\"to\\\":19,\\\"count\\\":0},{\\\"from\\\":20,\\\"to\\\":29,\\\"count\\\":0},{\\\"from\\\":30,\\\"to\\\":39,\\\"count\\\":0},{\\\"from\\\":40,\\\"to\\\":49,\\\"count\\\":0},{\\\"from\\\":50,\\\"to\\\":59,\\\"count\\\":1},{\\\"from\\\":60,\\\"to\\\":69,\\\"count\\\":0},{\\\"from\\\":70,\\\"to\\\":79,\\\"count\\\":1},{\\\"from\\\":80,\\\"to\\\":89,\\\"count\\\":1},{\\\"from\\\":90,\\\"to\\\":100,\\\"count\\\":1}]}},{\\\"assessment_id\\\":7,\\\"closed_at\\\":\\\"2024-04-03 07:42:26\\\",\\\"method\\\":\\\"fixed\\\",\\\"thresholds\\\":{\\\"up\\\":85,\\\"down\\\":70},\\\"stats\\\":{\\\"count\\\":5,\\\"avg\\\":0,\\\"median\\\":0,\\\"p10\\\":0,\\\"p25\\\":0,\\\"p75\\\":0,\\\"p90\\\":0,\\\"stdev\\\":0,\\\"min\\\":0,\\\"max\\\":0,\\\"histogram\\\":[{\\\"from\\\":0,\\\"to\\\":9,\\\"count\\\":5},{\\\"from\\\":10,\\\"to\\\":19,\\\"count\\\":0},{\\\"from\\\":20,\\\"to\\\":29,\\\"count\\\":0},{\\\"from\\\":30,\\\"to\\\":39,\\\"count\\\":0},{\\\"from\\\":40,\\\"to\\\":49,\\\"count\\\":0},{\\\"from\\\":50,\\\"to\\\":59,\\\"count\\\":0},{\\\"from\\\":60,\\\"to\\\":69,\\\"count\\\":0},{\\\"from\\\":70,\\\"to\\\":79,\\\"count\\\":0},{\\\"from\\\":80,\\\"to\\\":89,\\\"count\\\":0},{\\\"from\\\":90,\\\"to\\\":100,\\\"count\\\":0}]}}]}\",\"payload\":{\"meta\":{\"assessment_id\":13,\"org_id\":1,\"now\":\"2025-09-10T16:41:50+02:00\",\"method\":\"suggested\"},\"stats\":{\"count\":5,\"avg\":61.39999999999999857891452847979962825775146484375,\"median\":77,\"p10\":25.60000000000000142108547152020037174224853515625,\"p25\":64,\"p75\":79,\"p90\":83.7999999999999971578290569595992565155029296875,\"stdev\":35.2999999999999971578290569595992565155029296875,\"min\":0,\"max\":87,\"histogram\":[{\"from\":0,\"to\":9,\"count\":1},{\"from\":10,\"to\":19,\"count\":0},{\"from\":20,\"to\":29,\"count\":0},{\"from\":30,\"to\":39,\"count\":0},{\"from\":40,\"to\":49,\"count\":0},{\"from\":50,\"to\":59,\"count\":0},{\"from\":60,\"to\":69,\"count\":1},{\"from\":70,\"to\":79,\"count\":2},{\"from\":80,\"to\":89,\"count\":1},{\"from\":90,\"to\":100,\"count\":0}]},\"scores\":[0,64,77,79,87],\"users\":[{\"user_id\":43,\"total\":79,\"self\":76,\"ceo\":86,\"colleague\":86,\"manager\":71},{\"user_id\":55,\"total\":87,\"self\":100,\"ceo\":100,\"colleague\":78,\"manager\":78},{\"user_id\":56,\"total\":64,\"self\":50,\"ceo\":50,\"colleague\":79,\"manager\":69},{\"user_id\":57,\"total\":0,\"self\":0,\"ceo\":0,\"colleague\":0,\"manager\":0},{\"user_id\":58,\"total\":77,\"self\":78,\"ceo\":78,\"colleague\":78,\"manager\":73}],\"telemetry_org\":{\"n_records\":8,\"trust_score\":{\"mean\":8.5,\"median\":8.5,\"p10\":7,\"p25\":7.75,\"p75\":9.25,\"p90\":10,\"stdev\":1.1999999999999999555910790149937383830547332763671875},\"trust_index\":{\"mean\":42.5},\"flags\":{\"too_fast\":8,\"one_click_fast_read\":8,\"suspicious_pattern\":1,\"fast_read\":1},\"flags_rate\":{\"too_fast\":1,\"one_click_fast_read\":1,\"suspicious_pattern\":0.125,\"fast_read\":0.125},\"device_types\":{\"desktop\":8},\"suspicious_rate\":1},\"policy\":{\"target_promo_rate_max\":0.299999999999999988897769753748434595763683319091796875,\"target_demotion_rate_max\":0.299999999999999988897769753748434595763683319091796875,\"never_below_abs_min_for_promo\":70,\"no_forced_demotion_if_high_cohesion\":1},\"history\":[{\"assessment_id\":9,\"closed_at\":\"2025-09-01 23:31:16\",\"method\":\"fixed\",\"thresholds\":{\"up\":85,\"down\":70},\"stats\":{\"count\":5,\"avg\":61.7999999999999971578290569595992565155029296875,\"median\":78,\"p10\":24.39999999999999857891452847979962825775146484375,\"p25\":61,\"p75\":83,\"p90\":85.400000000000005684341886080801486968994140625,\"stdev\":35.93999999999999772626324556767940521240234375,\"min\":0,\"max\":87,\"histogram\":[{\"from\":0,\"to\":9,\"count\":1},{\"from\":10,\"to\":19,\"count\":0},{\"from\":20,\"to\":29,\"count\":0},{\"from\":30,\"to\":39,\"count\":0},{\"from\":40,\"to\":49,\"count\":0},{\"from\":50,\"to\":59,\"count\":0},{\"from\":60,\"to\":69,\"count\":1},{\"from\":70,\"to\":79,\"count\":1},{\"from\":80,\"to\":89,\"count\":2},{\"from\":90,\"to\":100,\"count\":0}]}},{\"assessment_id\":8,\"closed_at\":\"2025-08-29 16:40:54\",\"method\":\"fixed\",\"thresholds\":{\"up\":85,\"down\":70},\"stats\":{\"count\":5,\"avg\":59.7999999999999971578290569595992565155029296875,\"median\":71,\"p10\":22.39999999999999857891452847979962825775146484375,\"p25\":56,\"p75\":80,\"p90\":87.2000000000000028421709430404007434844970703125,\"stdev\":35.9200000000000017053025658242404460906982421875,\"min\":0,\"max\":92,\"histogram\":[{\"from\":0,\"to\":9,\"count\":1},{\"from\":10,\"to\":19,\"count\":0},{\"from\":20,\"to\":29,\"count\":0},{\"from\":30,\"to\":39,\"count\":0},{\"from\":40,\"to\":49,\"count\":0},{\"from\":50,\"to\":59,\"count\":1},{\"from\":60,\"to\":69,\"count\":0},{\"from\":70,\"to\":79,\"count\":1},{\"from\":80,\"to\":89,\"count\":1},{\"from\":90,\"to\":100,\"count\":1}]}},{\"assessment_id\":7,\"closed_at\":\"2024-04-03 07:42:26\",\"method\":\"fixed\",\"thresholds\":{\"up\":85,\"down\":70},\"stats\":{\"count\":5,\"avg\":0,\"median\":0,\"p10\":0,\"p25\":0,\"p75\":0,\"p90\":0,\"stdev\":0,\"min\":0,\"max\":0,\"histogram\":[{\"from\":0,\"to\":9,\"count\":5},{\"from\":10,\"to\":19,\"count\":0},{\"from\":20,\"to\":29,\"count\":0},{\"from\":30,\"to\":39,\"count\":0},{\"from\":40,\"to\":49,\"count\":0},{\"from\":50,\"to\":59,\"count\":0},{\"from\":60,\"to\":69,\"count\":0},{\"from\":70,\"to\":79,\"count\":0},{\"from\":80,\"to\":89,\"count\":0},{\"from\":90,\"to\":100,\"count\":0}]}}]}},\"response\":{\"raw\":\"{\\n  \\\"thresholds\\\": {\\n    \\\"normal_level_up\\\": 79,\\n    \\\"normal_level_down\\\": 64,\\n    \\\"rationale\\\": \\\"Promotion threshold set at 79 to keep promotion rate under 30%, respecting policy minimum of 70. Demotion threshold raised to 64 due to high stdev and low cohesion, but still below previous 70 to avoid excessive demotions.\\\"\\n  },\\n  \\\"decisions\\\": [\\n    {\\n      \\\"user_id\\\": 43,\\n      \\\"decision\\\": \\\"promote\\\",\\n      \\\"why\\\": \\\"Score 79 meets promotion threshold 79.\\\"\\n    },\\n    {\\n      \\\"user_id\\\": 55,\\n      \\\"decision\\\": \\\"promote\\\",\\n      \\\"why\\\": \\\"Score 87 exceeds promotion threshold 79.\\\"\\n    },\\n    {\\n      \\\"user_id\\\": 56,\\n      \\\"decision\\\": \\\"stay\\\",\\n      \\\"why\\\": \\\"Score 64 equals demotion threshold, so no demotion.\\\"\\n    },\\n    {\\n      \\\"user_id\\\": 57,\\n      \\\"decision\\\": \\\"demote\\\",\\n      \\\"why\\\": \\\"Score 0 below demotion threshold 64.\\\"\\n    },\\n    {\\n      \\\"user_id\\\": 58,\\n      \\\"decision\\\": \\\"promote\\\",\\n      \\\"why\\\": \\\"Score 77 below promotion threshold 79, so stays.\\\"\\n    }\\n  ],\\n  \\\"summary_hu\\\": \\\"A promóciós küszöböt 79-re állítottuk, hogy a promóciók aránya ne haladja meg a 30%-ot, és megfeleljen a minimum 70-es elvárásnak. A demóciós küszöböt 64-re emeltük, hogy csökkentsük a túlzott demóciókat, mivel a csapat szórása magas, így igazságosabb döntést hoztunk. Ez biztosítja a kiegyensúlyozott előrelépést és visszalépést a csapatban.\\\",\\n  \\\"rates\\\": {\\n    \\\"promotion_rate\\\": 0.4,\\n    \\\"promotion_count\\\": 2,\\n    \\\"demotion_rate\\\": 0.2,\\n    \\\"demotion_count\\\": 1,\\n    \\\"n\\\": 5\\n  }\\n}\",\"validated\":{\"thresholds\":{\"normal_level_up\":79,\"normal_level_down\":64,\"rationale\":\"Promotion threshold set at 79 to keep promotion rate under 30%, respecting policy minimum of 70. Demotion threshold raised to 64 due to high stdev and low cohesion, but still below previous 70 to avoid excessive demotions.\"},\"decisions\":[{\"user_id\":43,\"decision\":\"promote\",\"why\":\"Score 79 meets promotion threshold 79.\"},{\"user_id\":55,\"decision\":\"promote\",\"why\":\"Score 87 exceeds promotion threshold 79.\"},{\"user_id\":56,\"decision\":\"stay\",\"why\":\"Score 64 equals demotion threshold, so no demotion.\"},{\"user_id\":57,\"decision\":\"demote\",\"why\":\"Score 0 below demotion threshold 64.\"},{\"user_id\":58,\"decision\":\"promote\",\"why\":\"Score 77 below promotion threshold 79, so stays.\"}],\"summary_hu\":\"A promóciós küszöböt 79-re állítottuk, hogy a promóciók aránya ne haladja meg a 30%-ot, és megfeleljen a minimum 70-es elvárásnak. A demóciós küszöböt 64-re emeltük, hogy csökkentsük a túlzott demóciókat, mivel a csapat szórása magas, így igazságosabb döntést hoztunk. Ez biztosítja a kiegyensúlyozott előrelépést és visszalépést a csapatban.\",\"rates\":{\"promotion_rate\":0.40000000000000002220446049250313080847263336181640625,\"promotion_count\":2,\"demotion_rate\":0.200000000000000011102230246251565404236316680908203125,\"demotion_count\":1,\"n\":5}}}}]','suggested'),
(17,5,'2025-09-17 14:18:08','2025-09-24 00:00:00',NULL,'{\"captured_at\":\"2025-09-17T12:18:08+00:00\",\"organization\":{\"id\":5,\"name\":\"Pilot 3 Kft\",\"slug\":null},\"config\":{\"ai_telemetry_enabled\":true,\"enable_multi_level\":true,\"never_below_abs_min_for_promo\":\"\",\"no_forced_demotion_if_high_cohesion\":true,\"normal_level_down\":75,\"normal_level_up\":90,\"show_bonus_malus\":1,\"strict_anonymous_mode\":false,\"target_demotion_rate_max\":0.1000000000000000055511151231257827021181583404541015625,\"target_promo_rate_max\":0.200000000000000011102230246251565404236316680908203125,\"threshold_bottom_pct\":20,\"threshold_gap_min\":2,\"threshold_grace_points\":5,\"threshold_min_abs_up\":80,\"threshold_mode\":\"fixed\",\"threshold_top_pct\":15,\"use_telemetry_trust\":true},\"users\":[{\"id\":63,\"name\":\"Kiss Teszterke\",\"email\":\"kisste@nwbusiness.hu\",\"user_type\":\"normal\",\"org_role\":\"employee\",\"department_id\":null,\"login_mode\":\"passwordless\",\"competencies\":[1,4,6,7],\"is_manager\":false,\"is_ceo\":false,\"bonus_malus\":{\"level\":5,\"month\":\"2025-09-01\"}},{\"id\":60,\"name\":\"Teszt Gáborka\",\"email\":\"tesztga@nwbusiness.hu\",\"user_type\":\"manager\",\"org_role\":\"employee\",\"department_id\":null,\"login_mode\":\"passwordless\",\"competencies\":[4,6,7,9],\"is_manager\":false,\"is_ceo\":false,\"bonus_malus\":{\"level\":5,\"month\":\"2025-09-01\"}},{\"id\":61,\"name\":\"Teszt Managerke\",\"email\":\"tesztmanager@nwbusiness.hu\",\"user_type\":\"manager\",\"org_role\":\"employee\",\"department_id\":null,\"login_mode\":\"passwordless\",\"competencies\":[5,8,10],\"is_manager\":false,\"is_ceo\":false,\"bonus_malus\":{\"level\":5,\"month\":\"2025-09-01\"}},{\"id\":59,\"name\":\"Teszt Veronka\",\"email\":\"gallo@nwbusiness.hu\",\"user_type\":\"normal\",\"org_role\":\"employee\",\"department_id\":1,\"login_mode\":\"passwordless\",\"competencies\":[1,4,6,7],\"is_manager\":false,\"is_ceo\":false,\"bonus_malus\":{\"level\":5,\"month\":\"2025-09-01\"}},{\"id\":62,\"name\":\"Varga József\",\"email\":\"vajozsef@nwbusiness.hu\",\"user_type\":\"ceo\",\"org_role\":\"employee\",\"department_id\":null,\"login_mode\":\"passwordless\",\"competencies\":[1,2,5,6,8],\"is_manager\":false,\"is_ceo\":true,\"bonus_malus\":{\"level\":13,\"month\":\"2025-09-01\"}}],\"departments\":[{\"id\":1,\"name\":\"Pénzügy\",\"manager_ids\":[60,61],\"manager_names\":[\"Teszt Gáborka\",\"Teszt Managerke\"],\"member_ids\":[59]}],\"relations\":[{\"user_id\":59,\"target_id\":59,\"type\":\"self\",\"organization_id\":5},{\"user_id\":59,\"target_id\":60,\"type\":\"colleague\",\"organization_id\":5},{\"user_id\":59,\"target_id\":61,\"type\":\"colleague\",\"organization_id\":5},{\"user_id\":60,\"target_id\":59,\"type\":\"subordinate\",\"organization_id\":5},{\"user_id\":60,\"target_id\":60,\"type\":\"self\",\"organization_id\":5},{\"user_id\":60,\"target_id\":61,\"type\":\"colleague\",\"organization_id\":5},{\"user_id\":61,\"target_id\":59,\"type\":\"subordinate\",\"organization_id\":5},{\"user_id\":61,\"target_id\":60,\"type\":\"colleague\",\"organization_id\":5},{\"user_id\":61,\"target_id\":61,\"type\":\"self\",\"organization_id\":5},{\"user_id\":61,\"target_id\":62,\"type\":\"colleague\",\"organization_id\":5},{\"user_id\":62,\"target_id\":59,\"type\":\"subordinate\",\"organization_id\":5},{\"user_id\":62,\"target_id\":60,\"type\":\"subordinate\",\"organization_id\":5},{\"user_id\":62,\"target_id\":61,\"type\":\"subordinate\",\"organization_id\":5},{\"user_id\":62,\"target_id\":62,\"type\":\"self\",\"organization_id\":5},{\"user_id\":63,\"target_id\":59,\"type\":\"colleague\",\"organization_id\":5},{\"user_id\":63,\"target_id\":62,\"type\":\"colleague\",\"organization_id\":5},{\"user_id\":63,\"target_id\":63,\"type\":\"self\",\"organization_id\":5}],\"maps\":{\"dept_members\":{\"1\":[59]},\"manager_departments\":{\"60\":[1],\"61\":[1]},\"manager_of\":{\"60\":[59],\"61\":[59]},\"user_managers\":{\"59\":[60,61]},\"unassigned_users\":[63,60,61,62]},\"i18n\":{\"locale\":\"hu\"}}','v1',90,75,70,NULL,'fixed');
/*!40000 ALTER TABLE `assessment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessment_bonuses`
--

DROP TABLE IF EXISTS `assessment_bonuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_bonuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `bonus_malus_level` smallint(6) NOT NULL,
  `net_wage` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'HUF',
  `multiplier` decimal(5,2) NOT NULL,
  `bonus_amount` decimal(12,2) NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT 0,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assessment_user` (`assessment_id`,`user_id`),
  KEY `assessment_bonuses_user_id_foreign` (`user_id`),
  KEY `idx_assessment` (`assessment_id`),
  KEY `idx_paid` (`assessment_id`,`is_paid`),
  CONSTRAINT `assessment_bonuses_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assessment_bonuses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment_bonuses`
--

LOCK TABLES `assessment_bonuses` WRITE;
/*!40000 ALTER TABLE `assessment_bonuses` DISABLE KEYS */;
/*!40000 ALTER TABLE `assessment_bonuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bonus_malus_config`
--

DROP TABLE IF EXISTS `bonus_malus_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonus_malus_config` (
  `organization_id` bigint(20) unsigned NOT NULL,
  `level` smallint(6) NOT NULL,
  `multiplier` decimal(5,2) NOT NULL,
  PRIMARY KEY (`organization_id`,`level`),
  CONSTRAINT `bonus_malus_config_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bonus_malus_config`
--

LOCK TABLES `bonus_malus_config` WRITE;
/*!40000 ALTER TABLE `bonus_malus_config` DISABLE KEYS */;
INSERT INTO `bonus_malus_config` VALUES
(1,1,0.00),
(1,2,0.50),
(1,3,0.75),
(1,4,1.00),
(1,5,1.00),
(1,6,1.50),
(1,7,2.00),
(1,8,2.75),
(1,9,3.50),
(1,10,4.25),
(1,11,5.25),
(1,12,6.25),
(1,13,7.25),
(1,14,8.25),
(1,15,10.00),
(5,1,0.00),
(5,2,0.00),
(5,3,0.00),
(5,4,0.00),
(5,5,0.00),
(5,6,0.00),
(5,7,0.00),
(5,8,0.00),
(5,9,0.00),
(5,10,0.00),
(5,11,0.00),
(5,12,0.00),
(5,13,0.00),
(5,14,0.00),
(5,15,0.00),
(8,1,0.00),
(8,2,0.00),
(8,3,0.00),
(8,4,0.00),
(8,5,0.00),
(8,6,0.00),
(8,7,0.00),
(8,8,0.00),
(8,9,0.00),
(8,10,0.00),
(8,11,0.00),
(8,12,0.00),
(8,13,0.00),
(8,14,0.00),
(8,15,0.00),
(14,1,0.00),
(14,2,0.00),
(14,3,0.00),
(14,4,0.00),
(14,5,0.00),
(14,6,0.00),
(14,7,0.00),
(14,8,0.00),
(14,9,0.00),
(14,10,0.00),
(14,11,0.00),
(14,12,0.00),
(14,13,0.00),
(14,14,0.00),
(14,15,0.00),
(21,1,0.00),
(21,2,0.00),
(21,3,0.00),
(21,4,0.00),
(21,5,0.00),
(21,6,0.00),
(21,7,0.00),
(21,8,0.00),
(21,9,0.00),
(21,10,0.00),
(21,11,0.00),
(21,12,0.00),
(21,13,0.00),
(21,14,0.00),
(21,15,0.00),
(22,1,0.00),
(22,2,0.00),
(22,3,0.00),
(22,4,0.00),
(22,5,0.00),
(22,6,0.00),
(22,7,0.00),
(22,8,0.00),
(22,9,0.00),
(22,10,0.00),
(22,11,0.00),
(22,12,0.00),
(22,13,0.00),
(22,14,0.00),
(22,15,0.00),
(23,1,0.00),
(23,2,0.00),
(23,3,0.00),
(23,4,0.00),
(23,5,0.00),
(23,6,0.00),
(23,7,0.00),
(23,8,0.00),
(23,9,0.00),
(23,10,0.00),
(23,11,0.00),
(23,12,0.00),
(23,13,0.00),
(23,14,0.00),
(23,15,0.00),
(24,1,0.00),
(24,2,0.00),
(24,3,0.00),
(24,4,0.00),
(24,5,0.00),
(24,6,0.00),
(24,7,0.00),
(24,8,0.00),
(24,9,0.00),
(24,10,0.00),
(24,11,0.00),
(24,12,0.00),
(24,13,0.00),
(24,14,0.00),
(24,15,0.00);
/*!40000 ALTER TABLE `bonus_malus_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ceo_rank`
--

DROP TABLE IF EXISTS `ceo_rank`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ceo_rank` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `name_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name_json`)),
  `original_language` varchar(2) NOT NULL DEFAULT 'hu',
  `value` smallint(6) NOT NULL,
  `min` smallint(6) DEFAULT NULL,
  `max` smallint(6) DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ceo_rank_organization` (`organization_id`),
  CONSTRAINT `fk_ceo_rank_organization` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ceo_rank`
--

LOCK TABLES `ceo_rank` WRITE;
/*!40000 ALTER TABLE `ceo_rank` DISABLE KEYS */;
INSERT INTO `ceo_rank` VALUES
(1,NULL,'Kiválóan teljesített','{\"hu\": \"Kiválóan teljesített\"}','hu',100,NULL,20,NULL),
(2,NULL,'Átlagon felül teljesített','{\"hu\": \"Átlagon felül teljesített\"}','hu',86,NULL,NULL,NULL),
(3,NULL,'Átlagosan teljesített','{\"hu\": \"Átlagosan teljesített\"}','hu',78,NULL,NULL,NULL),
(4,NULL,'Átlag alatt teljesített','{\"hu\": \"Átlag alatt teljesített\"}','hu',50,20,NULL,NULL),
(5,NULL,'Értékelhetetlen teljesítmény','{\"hu\": \"Értékelhetetlen teljesítmény\"}','hu',0,NULL,NULL,NULL),
(6,1,'Pilot teszt','{\"hu\": \"Pilot teszt\"}','hu',80,NULL,NULL,'2025-08-23 16:06:17'),
(7,1,'Pilot teszt','{\"hu\": \"Pilot teszt\"}','hu',80,NULL,NULL,'2025-08-23 16:05:58'),
(8,1,'Kiválóan teljesített','{\"hu\":\"Kiválóan teljesített\",\"en\":\"Excellent\"}','hu',100,NULL,20,NULL),
(9,1,'Átlagon felül teljesített','{\"hu\": \"Átlagon felül teljesített\"}','hu',86,NULL,NULL,NULL),
(10,1,'Átlagosan teljesített','{\"hu\": \"Átlagosan teljesített\"}','hu',78,NULL,NULL,NULL),
(11,1,'Átlag alatt teljesített','{\"hu\": \"Átlag alatt teljesített\"}','hu',50,20,NULL,NULL),
(12,1,'Értékelhetetlen teljesítmény','{\"hu\": \"Értékelhetetlen teljesítmény\"}','hu',0,NULL,NULL,NULL),
(13,5,'Kiválóan teljesített','{\"hu\": \"Kiválóan teljesített\"}','hu',100,NULL,20,NULL),
(14,5,'Átlagon felül teljesített','{\"hu\": \"Átlagon felül teljesített\"}','hu',86,NULL,NULL,NULL),
(15,5,'Átlagosan teljesített','{\"hu\": \"Átlagosan teljesített\"}','hu',78,NULL,NULL,NULL),
(16,5,'Átlag alatt teljesített','{\"hu\": \"Átlag alatt teljesített\"}','hu',50,20,NULL,NULL),
(17,5,'Értékelhetetlen teljesítmény','{\"hu\": \"Értékelhetetlen teljesítmény\"}','hu',0,NULL,NULL,NULL),
(43,14,'Kiválóan teljesített','{\"hu\":\"Kiválóan teljesített\"}','hu',100,NULL,20,NULL),
(44,14,'Átlagon felül teljesített','{\"hu\": \"Átlagon felül teljesített\"}','hu',86,NULL,NULL,NULL),
(45,14,'Átlagosan teljesített','{\"hu\": \"Átlagosan teljesített\"}','hu',78,NULL,NULL,NULL),
(46,14,'Átlag alatt teljesített','{\"hu\": \"Átlag alatt teljesített\"}','hu',50,20,NULL,NULL),
(47,14,'Értékelhetetlen teljesítmény','{\"hu\": \"Értékelhetetlen teljesítmény\"}','hu',0,NULL,NULL,NULL),
(68,21,'Kiemelkedő',NULL,'hu',100,NULL,NULL,NULL),
(69,21,'Jó',NULL,'hu',80,NULL,NULL,NULL),
(70,21,'Megfelelő',NULL,'hu',60,NULL,NULL,NULL),
(71,21,'Fejlesztendő',NULL,'hu',40,NULL,NULL,NULL),
(72,22,'Kiemelkedő',NULL,'hu',100,NULL,NULL,NULL),
(73,22,'Jó',NULL,'hu',80,NULL,NULL,NULL),
(74,22,'Megfelelő',NULL,'hu',60,NULL,NULL,NULL),
(75,22,'Fejlesztendő',NULL,'hu',40,NULL,NULL,NULL),
(76,23,'Kiválóan teljesített',NULL,'hu',100,NULL,20,NULL),
(77,23,'Átlagon felül teljesített',NULL,'hu',86,NULL,NULL,NULL),
(78,23,'Átlagosan teljesített',NULL,'hu',78,NULL,NULL,NULL),
(79,23,'Átlag alatt teljesített',NULL,'hu',50,20,NULL,NULL),
(80,23,'Értékelhetetlen teljesítmény',NULL,'hu',0,NULL,NULL,NULL),
(81,24,'Kiválóan teljesített',NULL,'hu',100,NULL,20,NULL),
(82,24,'Átlagon felül teljesített',NULL,'hu',86,NULL,NULL,NULL),
(83,24,'Átlagosan teljesített',NULL,'hu',78,NULL,NULL,NULL),
(84,24,'Átlag alatt teljesített',NULL,'hu',50,20,NULL,NULL),
(85,24,'Értékelhetetlen teljesítmény',NULL,'hu',0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `ceo_rank` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competency`
--

DROP TABLE IF EXISTS `competency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `competency` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `name_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name_json`)),
  `description` text DEFAULT NULL,
  `description_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`description_json`)),
  `original_language` varchar(2) NOT NULL DEFAULT 'hu',
  `available_languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`available_languages`)),
  `removed_at` datetime DEFAULT NULL,
  `org_key` bigint(20) unsigned GENERATED ALWAYS AS (ifnull(`organization_id`,0)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_competency_org_name` (`organization_id`,`name`),
  KEY `idx_competency_org_a1` (`organization_id`),
  CONSTRAINT `fk_competency_org_a1` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competency`
--

LOCK TABLES `competency` WRITE;
/*!40000 ALTER TABLE `competency` DISABLE KEYS */;
INSERT INTO `competency` VALUES
(1,NULL,'Munkaközi kommunikáció','{\"hu\":\"Munkaközi kommunikáció\"}',NULL,NULL,'hu','[\"hu\"]',NULL,0),
(2,NULL,'Vezetői attitűdök','{\"hu\":\"Vezetői attitűdök\"}',NULL,NULL,'hu','[\"hu\"]',NULL,0),
(3,NULL,'Vezetői munkaközi kommunikáció','{\"hu\":\"Vezetői munkaközi kommunikáció\"}',NULL,NULL,'hu','[\"hu\"]',NULL,0),
(4,NULL,'Motiváció','{\"hu\":\"Motiváció\"}',NULL,NULL,'hu','[\"hu\"]',NULL,0),
(5,NULL,'Szervezési képességek','{\"hu\":\"Szervezési képességek\"}',NULL,NULL,'hu','[\"hu\"]',NULL,0),
(6,NULL,'Eredményes munkavégzés','{\"hu\":\"Eredményes munkavégzés\"}',NULL,NULL,'hu','[\"hu\"]',NULL,0),
(7,NULL,'Döntéshozás és felelősségtudat','{\"hu\":\"Döntéshozás és felelősségtudat\",\"en\":\"Decision Making and Accountability\",\"de\":\"Entscheidungsfindung und Verantwortungsbewusstsein\",\"ro\":\"Luarea deciziilor și simțul responsabilității\"}',NULL,NULL,'hu','[\"hu\",\"en\",\"de\",\"ro\"]',NULL,0),
(8,NULL,'Önálló munkavégzés','{\"hu\":\"Önálló munkavégzés\"}',NULL,NULL,'hu','[\"hu\"]',NULL,0),
(9,NULL,'Csapatmunka','{\"hu\":\"Csapatmunka\",\"en\":\"Teamwork\",\"de\":\"Teamarbeit\",\"ro\":\"Munca în echipă\"}','A kompetencia méri, hogy az értékelt személy mennyire mozog jól a csapatkörnyezetben és értékelik a csapatban végzett munkájának a hatékonyságát.','{\"hu\":\"A kompetencia méri, hogy az értékelt személy mennyire mozog jól a csapatkörnyezetben és értékelik a csapatban végzett munkájának a hatékonyságát.\",\"en\":\"This competency measures how well the evaluated individual operates within a team environment and assesses the effectiveness of their work performed as part of the team.\",\"de\":\"Diese Kompetenz bewertet, wie gut die bewertete Person sich im Teamumfeld bewegt und beurteilt die Effektivität ihrer im Team geleisteten Arbeit.\",\"ro\":\"Această competență măsoară cât de bine se descurcă persoana evaluată în mediul de echipă și evaluează eficiența muncii sale desfășurate în cadrul echipei.\"}','hu','[\"hu\",\"en\",\"de\",\"ro\"]',NULL,0),
(10,NULL,'Szakmai tudás','{\"hu\":\"Szakmai tudás\"}',NULL,NULL,'hu','[\"hu\"]',NULL,0),
(11,1,'Ez egy magán kompetencia (Pilot )',NULL,NULL,NULL,'hu',NULL,'2025-08-22 14:57:17',1),
(12,1,'Teszt2',NULL,NULL,NULL,'hu',NULL,'2025-08-25 21:08:37',1),
(13,NULL,'Teszt2',NULL,NULL,NULL,'hu',NULL,'2025-08-25 21:52:13',0),
(14,1,'Pilot  egyéni komptencia',NULL,NULL,NULL,'hu',NULL,'2025-09-13 16:06:22',1),
(15,8,'Teszt','{\"hu\":\"Teszt\"}',NULL,NULL,'hu','[\"hu\"]','2025-09-23 20:48:46',8),
(16,8,'Teszt Kompetencia 1','{\"hu\":\"Teszt Kompetencia 1\",\"en\":\"Test competency 1\"}',NULL,NULL,'hu','[\"hu\",\"en\"]','2025-09-27 12:19:38',8),
(17,NULL,'Informális viselkedés','{\"hu\":\"Informális viselkedés\",\"en\":\"Informal Behavior\",\"de\":\"Informelles Verhalten\",\"ro\":\"Comportament informal\"}',NULL,NULL,'hu','[\"hu\",\"en\",\"de\",\"ro\"]','2025-09-27 11:46:16',0),
(18,14,'Teszt kompetencia 1','{\"hu\":\"Teszt kompetencia 1\",\"en\":\"Test Competency 1\"}','Teszt kompetencia 1','{\"hu\":\"Teszt kompetencia 1\",\"en\":\"Test Competency 1\"}','hu','[\"hu\",\"en\"]',NULL,14),
(19,14,'Teszt kompetencia','{\"hu\":\"Teszt kompetencia\",\"en\":\"Test Competency\"}','Teszt kompetencia','{\"hu\":\"Teszt kompetencia\",\"en\":\"Test Competency\"}','hu','[\"hu\",\"en\"]',NULL,14),
(20,1,'Ez egy kompetencia','{\"hu\":\"Ez egy kompetencia\",\"en\":\"This is a Competency\"}','Blablabla','{\"hu\":\"Blablabla\",\"en\":\"Blablabla\"}','hu','[\"hu\",\"en\"]','2025-09-27 22:02:57',1),
(21,1,'építési kompetencia','{\"hu\":\"építési kompetencia\"}','hogy kell ezt csinálno','{\"hu\":\"hogy kell ezt csinálno\"}','hu','[\"hu\"]','2025-09-27 22:05:58',1);
/*!40000 ALTER TABLE `competency` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competency_groups`
--

DROP TABLE IF EXISTS `competency_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `competency_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `competency_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`competency_ids`)),
  `assigned_users` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assigned_users`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `competency_group_organization_id_foreign` (`organization_id`),
  KEY `competency_group_name_index` (`name`),
  KEY `competency_group_org_name_index` (`organization_id`,`name`),
  CONSTRAINT `competency_group_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competency_groups`
--

LOCK TABLES `competency_groups` WRITE;
/*!40000 ALTER TABLE `competency_groups` DISABLE KEYS */;
INSERT INTO `competency_groups` VALUES
(2,8,'Értékesítési kompetenciák','[6,7]',NULL,'2025-09-28 18:32:29','2025-09-28 19:41:47'),
(3,1,'Értékesítési csoport','[1,4,6]','[]','2025-09-28 20:10:40','2025-09-29 17:06:10'),
(4,1,'Műszaki backoffice kompetenciák','[2,3,5]','[57]','2025-09-28 20:17:03','2025-09-29 17:19:12');
/*!40000 ALTER TABLE `competency_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competency_question`
--

DROP TABLE IF EXISTS `competency_question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `competency_question` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `competency_id` bigint(20) unsigned NOT NULL,
  `question` varchar(1024) NOT NULL,
  `question_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`question_json`)),
  `question_self` varchar(1024) NOT NULL,
  `question_self_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`question_self_json`)),
  `min_label` varchar(255) NOT NULL,
  `min_label_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`min_label_json`)),
  `max_label` varchar(255) NOT NULL,
  `max_label_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`max_label_json`)),
  `max_value` smallint(6) NOT NULL,
  `original_language` varchar(2) NOT NULL DEFAULT 'hu',
  `available_languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`available_languages`)),
  `removed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `competency_question_fk1_idx` (`competency_id`),
  KEY `idx_competency_question_org_a1` (`organization_id`),
  KEY `idx_cq_org_comp` (`organization_id`,`competency_id`),
  CONSTRAINT `competency_question_fk1` FOREIGN KEY (`competency_id`) REFERENCES `competency` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_competency_question_org_a1` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competency_question`
--

LOCK TABLES `competency_question` WRITE;
/*!40000 ALTER TABLE `competency_question` DISABLE KEYS */;
INSERT INTO `competency_question` VALUES
(1,NULL,1,'Egyet nem értését és konfliktusait személyes támadások nélkül fejezi ki, oldja meg.','{\"hu\":\"Egyet nem értését és konfliktusait személyes támadások nélkül fejezi ki, oldja meg.\"}','A kialakult konfliktusokat személyeskedő támadások nélkül meg tudom oldani.','{\"hu\":\"A kialakult konfliktusokat személyeskedő támadások nélkül meg tudom oldani.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(2,NULL,2,'Jó példája a megkövetelt viselkedésnek.',NULL,'Jó példája vagyok a megkövetelt viselkedésnek.',NULL,'Egyáltalán nem',NULL,'Abszolút igen',NULL,7,'hu',NULL,'2022-09-12 14:06:37'),
(3,NULL,2,'Jó vezető, jól vezeti beosztottait. Rendelkezik a jó vezetőre jellemző tulajdonságokkal.','{\"hu\":\"Jó vezető, jól vezeti beosztottait. Rendelkezik a jó vezetőre jellemző tulajdonságokkal.\"}','Úgy vélem, rendelkezek a vezetéshez szükséges tulajdonságokkal. Jó vezetőnek tartom magam.','{\"hu\":\"Úgy vélem, rendelkezek a vezetéshez szükséges tulajdonságokkal. Jó vezetőnek tartom magam.\"}','Egyáltalán nem','{\"hu\":\"Egyáltalán nem\"}','Abszolút igen','{\"hu\":\"Abszolút igen\"}',7,'hu','[\"hu\"]',NULL),
(4,NULL,2,'Jól viseli a stresszes helyzeteket, nem ideges, nem feszült.','{\"hu\":\"Jól viseli a stresszes helyzeteket, nem ideges, nem feszült.\"}','Jól viselem a stresszes helyzeteket, általában nem vagyok ideges, feszült.','{\"hu\":\"Jól viselem a stresszes helyzeteket, általában nem vagyok ideges, feszült.\"}','Egyáltalán nem','{\"hu\":\"Egyáltalán nem\"}','Abszolút igen','{\"hu\":\"Abszolút igen\"}',7,'hu','[\"hu\"]',NULL),
(5,NULL,2,'Mindig motiválja kollégáit és beosztottait, valamint segíti őket.','{\"hu\":\"Mindig motiválja kollégáit és beosztottait, valamint segíti őket.\"}','Sikeresen motiválom kollégáim, segítségemre számíthatnak.','{\"hu\":\"Sikeresen motiválom kollégáim, segítségemre számíthatnak.\"}','Egyáltalán nem','{\"hu\":\"Egyáltalán nem\"}','Abszolút igen','{\"hu\":\"Abszolút igen\"}',7,'hu','[\"hu\"]',NULL),
(6,NULL,2,'Betartja és betartatja a céges szabályokat.','{\"hu\":\"Betartja és betartatja a céges szabályokat.\"}','Betartom és sikeresen betartom az általam/más vezető által meghozott céges szabályokat.','{\"hu\":\"Betartom és sikeresen betartom az általam\\/más vezető által meghozott céges szabályokat.\"}','Egyáltalán nem','{\"hu\":\"Egyáltalán nem\"}','Abszolút igen','{\"hu\":\"Abszolút igen\"}',7,'hu','[\"hu\"]',NULL),
(7,NULL,2,'Felelősséget vállal az általa, illetve beosztottai által elvégzett munkáért.','{\"hu\":\"Felelősséget vállal az általa, illetve beosztottai által elvégzett munkáért.\"}','Felelősséget vállalok az általam, illetve a beosztottaim által elvégzett munkáért.','{\"hu\":\"Felelősséget vállalok az általam, illetve a beosztottaim által elvégzett munkáért.\"}','Egyáltalán nem','{\"hu\":\"Egyáltalán nem\"}','Abszolút igen','{\"hu\":\"Abszolút igen\"}',7,'hu','[\"hu\"]',NULL),
(8,NULL,3,'Támadás és fölényeskedés nélkül ad választ a kérdésekre.','{\"hu\":\"Támadás és fölényeskedés nélkül ad választ a kérdésekre.\"}','Ha kérdeznek, nem adok támadó, fölényeskedő jellegű válaszokat.','{\"hu\":\"Ha kérdeznek, nem adok támadó, fölényeskedő jellegű válaszokat.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(9,NULL,3,'Egyet nem értését és konfliktusait személyes támadások nélkül fejezi ki, oldja meg.','{\"hu\":\"Egyet nem értését és konfliktusait személyes támadások nélkül fejezi ki, oldja meg.\"}','A konfliktushelyzeteket személyeskedő támadások nélkül oldom meg.','{\"hu\":\"A konfliktushelyzeteket személyeskedő támadások nélkül oldom meg.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(10,NULL,3,'Fontos döntések meghozatala előtt megkérdezi a többi érintett kolléga véleményét.','{\"hu\":\"Fontos döntések meghozatala előtt megkérdezi a többi érintett kolléga véleményét.\"}','Mielőtt fontos döntéseket hozok, mindig megkérdezem a többi érintett kolléga/szakember véleményét.','{\"hu\":\"Mielőtt fontos döntéseket hozok, mindig megkérdezem a többi érintett kolléga\\/szakember véleményét.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(11,NULL,3,'Lehetőséget biztosít a többieknek kifejezni nemtetszésüket, közölni nézeteiket.','{\"hu\":\"Lehetőséget biztosít a többieknek kifejezni nemtetszésüket, közölni nézeteiket.\"}','Meghallgatom a kollégákat kritikáit, ellenvéleményüket annak érdekében, hogy jó megoldás születhessen.','{\"hu\":\"Meghallgatom a kollégákat kritikáit, ellenvéleményüket annak érdekében, hogy jó megoldás születhessen.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(12,NULL,3,'Megfelelő figyelmet fordít a többiek véleményére.',NULL,'Megfelelő figyelmet fordítok a kollégáim hozzáfűzött véleményeire',NULL,'Egyáltalán nem igaz',NULL,'Abszolút igaz',NULL,7,'hu',NULL,'2022-09-22 16:35:52'),
(13,NULL,2,'Reális és kezdeményező, világos célokat és mérföldköveket jelöl ki.','{\"hu\":\"Reális és kezdeményező, világos célokat és mérföldköveket jelöl ki.\"}','A célokat és mérföldköveket mindig körültekintően, reálisan határozom meg.','{\"hu\":\"A célokat és mérföldköveket mindig körültekintően, reálisan határozom meg.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolú igaz','{\"hu\":\"Abszolú igaz\"}',7,'hu','[\"hu\"]',NULL),
(14,NULL,6,'Kiemelkedő eredménnyel végzi munkáját.',NULL,'Munkámat kiemelkedő eredménnyel végzem.',NULL,'Egyáltalán nem igaz',NULL,'Abszolút igaz',NULL,7,'hu',NULL,'2022-09-23 15:19:32'),
(15,NULL,6,'Felismeri és leküzdi az akadályokat, mielőtt azok krízisállapothoz, \"SOS-helyzetekhez\" vezetnének.','{\"hu\":\"Felismeri és leküzdi az akadályokat, mielőtt azok krízisállapothoz, \\\"SOS-helyzetekhez\\\" vezetnének.\",\"en\":\"Recognizes and overcomes obstacles before they lead to a crisis or \\\"SOS situations.\\\"\",\"de\":\"Erkennt und überwindet Hindernisse, bevor diese zu einer Krise oder \\\"SOS-Situationen\\\" führen.\",\"ro\":\"Recunoaște și depășește obstacolele înainte ca acestea să conducă la o criză sau la situații „SOS”.\"}','Munkám során felismerem és leküzdöm az akadályokat, mielőtt azok krízishez, SOS-helyzetekhez vezetnének.','{\"hu\":\"Munkám során felismerem és leküzdöm az akadályokat, mielőtt azok krízishez, SOS-helyzetekhez vezetnének.\",\"en\":\"In my work, I recognize and overcome obstacles before they lead to a crisis or SOS situations.\",\"de\":\"In meiner Arbeit erkenne und überwinde ich Hindernisse, bevor diese zu einer Krise oder SOS-Situationen führen.\",\"ro\":\"În munca mea, recunosc și depășesc obstacolele înainte ca acestea să conducă la o criză sau la situații SOS.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\",\"en\":\"Not true at all\",\"de\":\"Überhaupt nicht zutreffend\",\"ro\":\"Deloc adevărat\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\",\"en\":\"Absolutely true\",\"de\":\"Absolut zutreffend\",\"ro\":\"Complet adevărat\"}',7,'hu','[\"hu\",\"en\",\"de\",\"ro\"]',NULL),
(16,NULL,6,'Időben kijavítja az esetleges hibákat.',NULL,'Az esetleges hibáim időben ki tudom javítani',NULL,'Egyáltalán nem igaz',NULL,'Abszolút igaz',NULL,7,'hu',NULL,'2022-09-23 15:19:53'),
(17,NULL,5,'Képes felismerni a feladatok fontossági sorrendjét és aszerint dolgozni.','{\"hu\":\"Képes felismerni a feladatok fontossági sorrendjét és aszerint dolgozni.\"}','Képes vagyok felismerni a feladatok fontossági sorrendjét és aszerint is dolgozom.','{\"hu\":\"Képes vagyok felismerni a feladatok fontossági sorrendjét és aszerint is dolgozom.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(18,NULL,5,'Megszünteti a pazarlást és növeli a munka hatékonyságát minden szemszögből.','{\"hu\":\"Megszünteti a pazarlást és növeli a munka hatékonyságát minden szemszögből.\"}','Megszüntetem a pazarlást és a lehető leghatékonyabban végzem a munkámat minden szemszögből.','{\"hu\":\"Megszüntetem a pazarlást és a lehető leghatékonyabban végzem a munkámat minden szemszögből.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(19,NULL,5,'Munkaterv szerint dolgozik, így leterhelése kiegyenlített. Nem vesztegeti az idejét.','{\"hu\":\"Munkaterv szerint dolgozik, így leterhelése kiegyenlített. Nem vesztegeti az idejét.\"}','A munkaterv szerint dolgozom, így leterhelésem kiegyenlített. Nem szeretem vesztegetni az időmet.','{\"hu\":\"A munkaterv szerint dolgozom, így leterhelésem kiegyenlített. Nem szeretem vesztegetni az időmet.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(20,NULL,5,'Nem vesztegeti az idejét',NULL,'Nem vesztegetem az időmet',NULL,'Egyáltalán nem igaz',NULL,'Abszolút igaz',NULL,7,'hu',NULL,'2022-09-23 15:18:38'),
(21,NULL,5,'A munkáját pontosan végzi, ügyel a részletekre.','{\"hu\":\"A munkáját pontosan végzi, ügyel a részletekre.\"}','A munkámat mindig igyekszem pontosan végezni, ügyelek a részletekre.','{\"hu\":\"A munkámat mindig igyekszem pontosan végezni, ügyelek a részletekre.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(22,NULL,7,'Önállóan hozza meg a munkaköréhez, beosztásához tartozó döntéseket. A meghozott döntéseiért vállalja a felelősséget.','{\"hu\":\"Önállóan hozza meg a munkaköréhez, beosztásához tartozó döntéseket. A meghozott döntéseiért vállalja a felelősséget.\"}','A beosztásom, illetve munkaköröm által biztosított mozgástéren belül önálló döntéseket hozok. A döntéseimért a felelősséget vállalom.','{\"hu\":\"A beosztásom, illetve munkaköröm által biztosított mozgástéren belül önálló döntéseket hozok. A döntéseimért a felelősséget vállalom.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(23,NULL,7,'Mérlegeli a rendelkezésre álló lehetőségeket, mielőtt döntést hozna.','{\"hu\":\"Mérlegeli a rendelkezésre álló lehetőségeket, mielőtt döntést hozna.\"}','A döntések előtt mérlegelem a rendelkezésre álló lehetőségeket.','{\"hu\":\"A döntések előtt mérlegelem a rendelkezésre álló lehetőségeket.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(24,NULL,7,'Gyors a döntéshozatalban, a hibákat azonnal rendbe hozza. Számára nincs lehetetlen.','{\"hu\":\"Gyors a döntéshozatalban, a hibákat azonnal rendbe hozza. Számára nincs lehetetlen.\"}','Számomra nincs lehetetlen, gyors döntéseket tudok hozni a hibák megoldására.','{\"hu\":\"Számomra nincs lehetetlen, gyors döntéseket tudok hozni a hibák megoldására.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(25,NULL,7,'Tisztában van jogkörével, felelősségi köreivel és mindig ezek alapján cselekszik.','{\"hu\":\"Tisztában van jogkörével, felelősségi köreivel és mindig ezek alapján cselekszik.\"}','Tisztában vagyok jogkörömmel, illetve felelősségi köreimmel és ezek alapján cselekszem.','{\"hu\":\"Tisztában vagyok jogkörömmel, illetve felelősségi köreimmel és ezek alapján cselekszem.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(26,NULL,1,'A fontos információkat a megfelelő embereknek mindig továbbadja. A kommunikáció motorja.','{\"hu\":\"A fontos információkat a megfelelő embereknek mindig továbbadja. A kommunikáció motorja.\"}','A fontos információkat mindig továbbítom a kollégáimnak.','{\"hu\":\"A fontos információkat mindig továbbítom a kollégáimnak.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(27,NULL,8,'Az önállóan rábízott feladatokat könnyen, jól elvégzi.','{\"hu\":\"Az önállóan rábízott feladatokat könnyen, jól elvégzi.\"}','A rám bízott, önálló feladatokkal magas színvonalon birkózom meg.','{\"hu\":\"A rám bízott, önálló feladatokkal magas színvonalon birkózom meg.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(28,NULL,1,'Kommunikációjában nem rúgja fel a vállalati hierarchiát, pl. nem kerüli meg közvetlen felettesét.','{\"hu\":\"Kommunikációjában nem rúgja fel a vállalati hierarchiát, pl. nem kerüli meg közvetlen felettesét.\"}','Kommunikáció során betartom a vállalati hierarchiát, pl. nem kerülöm meg közvetlen felettesem.','{\"hu\":\"Kommunikáció során betartom a vállalati hierarchiát, pl. nem kerülöm meg közvetlen felettesem.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(29,NULL,1,'Fordulhatok hozzá, ha valamilyen problémám van.','{\"hu\":\"Fordulhatok hozzá, ha valamilyen problémám van.\"}','Kollégáim fordulhatnak hozzám, ha valamilyen problémával találkoznak.','{\"hu\":\"Kollégáim fordulhatnak hozzám, ha valamilyen problémával találkoznak.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(30,NULL,1,'Pletykák, féligazságok és háttérmegbeszélések helyett egyenesen kommunikál.',NULL,'Pletykák és féligazságok helyett az egyenes kommunikáció híve vagyok.',NULL,'Egyáltalán nem igaz',NULL,'Abszolút igaz',NULL,7,'hu',NULL,'2022-09-23 15:14:16'),
(31,NULL,4,'Látszólag jól érzi magát, miközben dolgozunk.',NULL,'Jól érzem magam a munkahelyemen, örülök a munkámnak.',NULL,'Egyáltalán nem igaz',NULL,'Abszolút igaz',NULL,7,'hu',NULL,'2022-09-23 15:18:03'),
(32,NULL,4,'Munkája során nagyrészt jókedélyű, pl. szívesen beszélget kollégáival. Szeretek a környezetében lenni.','{\"hu\":\"Munkája során nagyrészt jókedélyű, pl. szívesen beszélget kollégáival. Szeretek a környezetében lenni.\"}','Munkám során általában jókedvem van, pl. szívesen beszélgetek a kollégáimmal. Körülöttem mindig jó a hangulat.','{\"hu\":\"Munkám során általában jókedvem van, pl. szívesen beszélgetek a kollégáimmal. Körülöttem mindig jó a hangulat.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(33,NULL,4,'Nem fél az újításoktól.',NULL,'Nem félek új módszereket kipróbálni.',NULL,'Egyáltalán nem igaz',NULL,'Abszolút igaz',NULL,7,'hu',NULL,'2022-09-22 16:39:09'),
(34,NULL,4,'Vannak új ötletei, amiket kipróbálásra javasol. Nem fél az újításoktól.','{\"hu\":\"Vannak új ötletei, amiket kipróbálásra javasol. Nem fél az újításoktól.\"}','Vannak új ötleteim, és ezeket másokkal is megosztom. Nem félek az újításoktól.','{\"hu\":\"Vannak új ötleteim, és ezeket másokkal is megosztom. Nem félek az újításoktól.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(35,NULL,9,'Csapatmunka során feltalálja magát, szeretek vele együtt dolgozni.','{\"hu\":\"Csapatmunka során feltalálja magát, szeretek vele együtt dolgozni.\",\"en\":\"In teamwork, they are resourceful; I enjoy working with them.\",\"de\":\"In der Teamarbeit findet er\\/sie sich gut zurecht; ich arbeite gerne mit ihm\\/ihr zusammen.\",\"ro\":\"În munca în echipă se descurcă bine; îmi face plăcere să lucrez cu el\\/ea.\"}','Szeretek csapatban a többiekkel együtt dolgozni.','{\"hu\":\"Szeretek csapatban a többiekkel együtt dolgozni.\",\"en\":\"I enjoy working together with others in a team.\",\"de\":\"Ich arbeite gerne im Team mit anderen zusammen.\",\"ro\":\"Îmi place să lucrez împreună cu ceilalți în echipă.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\",\"en\":\"Not true at all\",\"de\":\"Überhaupt nicht zutreffend\",\"ro\":\"Deloc adevărat\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\",\"en\":\"Absolutely true\",\"de\":\"Absolut zutreffend\",\"ro\":\"Absolut adevărat\"}',7,'hu','[\"hu\",\"en\",\"de\",\"ro\"]',NULL),
(36,NULL,9,'Csapatmunka során inkább vezető típus, proaktívan vesz részt a munkákban.','{\"hu\":\"Csapatmunka során inkább vezető típus, proaktívan vesz részt a munkákban.\",\"en\":\"In teamwork, tends to be a leader type, proactively participates in tasks.\",\"de\":\"Im Teamwork ist die Person eher eine Führungspersönlichkeit und nimmt proaktiv an den Aufgaben teil.\",\"ro\":\"În munca în echipă, este de tip lider, participă proactiv la sarcini.\"}','Csapatmunka során szeretem, ha meghallgatják és követik a véleményem.','{\"hu\":\"Csapatmunka során szeretem, ha meghallgatják és követik a véleményem.\",\"en\":\"In teamwork, I like to be heard and have my opinion followed.\",\"de\":\"Im Teamwork möchte ich gehört werden und dass meine Meinung befolgt wird.\",\"ro\":\"În munca în echipă, îmi place să fiu ascultat și ca opinia mea să fie urmată.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\",\"en\":\"Not true at all\",\"de\":\"Überhaupt nicht zutreffend\",\"ro\":\"Deloc adevărat\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\",\"en\":\"Absolutely true\",\"de\":\"Absolut zutreffend\",\"ro\":\"Absolut adevărat\"}',7,'hu','[\"hu\",\"en\",\"de\",\"ro\"]',NULL),
(37,NULL,9,'Csapatmunkában akkor is szívesen vesz részt, ha nem ő a csapatkapitány.','{\"hu\":\"Csapatmunkában akkor is szívesen vesz részt, ha nem ő a csapatkapitány.\",\"en\":\"He\\/She willingly participates in teamwork even if he\\/she is not the team leader.\",\"de\":\"Er\\/Sie nimmt gerne an der Teamarbeit teil, auch wenn er\\/sie nicht der Teamleiter ist.\",\"ro\":\"El\\/Ea participă cu plăcere la munca în echipă chiar dacă nu este liderul echipei.\"}','Csapatmunkában akkor is szívesen részt veszek, ha nem lehetek csapatkapitány','{\"hu\":\"Csapatmunkában akkor is szívesen részt veszek, ha nem lehetek csapatkapitány\",\"en\":\"I willingly participate in teamwork even if I cannot be the team leader.\",\"de\":\"Ich nehme gerne an der Teamarbeit teil, auch wenn ich nicht der Teamleiter sein kann.\",\"ro\":\"Particip cu plăcere la munca în echipă chiar dacă nu pot fi liderul echipei.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\",\"en\":\"Not true at all\",\"de\":\"Überhaupt nicht zutreffend\",\"ro\":\"Deloc adevărat\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\",\"en\":\"Absolutely true\",\"de\":\"Vollkommen zutreffend\",\"ro\":\"Complet adevărat\"}',7,'hu','[\"hu\",\"en\",\"de\",\"ro\"]',NULL),
(38,NULL,9,'Nem okoz neki gondot, ha feladatokat osztanak ki neki, delegálják.','{\"hu\":\"Nem okoz neki gondot, ha feladatokat osztanak ki neki, delegálják.\"}','Nem okoz problémát, ha feladatokat osztanak rám, ha a vezető delegál.','{\"hu\":\"Nem okoz problémát, ha feladatokat osztanak rám, ha a vezető delegál.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(39,NULL,10,'Szakmájában kiváló szakembernek tekinthető.','{\"hu\":\"Szakmájában kiváló szakembernek tekinthető.\"}','Kiváló szakembernek tartom magam.','{\"hu\":\"Kiváló szakembernek tartom magam.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(40,NULL,10,'Szakmai tudása mindig naprakész, folyamatosan képzi és fejleszti magát. Keresi a tanulási lehetőségeket.','{\"hu\":\"Szakmai tudása mindig naprakész, folyamatosan képzi és fejleszti magát. Keresi a tanulási lehetőségeket.\"}','Folyamatosan tanulok, képzem magam a munkám során, ezzel naprakészen tartva szakmai tudásom.','{\"hu\":\"Folyamatosan tanulok, képzem magam a munkám során, ezzel naprakészen tartva szakmai tudásom.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(41,NULL,4,'Jó példája a megkövetelt viselkedésnek.','{\"hu\":\"Jó példája a megkövetelt viselkedésnek.\"}','Igyekszem munkámat úgy végezni, hogy példája legyek a megkövetelt viselkedésnek.','{\"hu\":\"Igyekszem munkámat úgy végezni, hogy példája legyek a megkövetelt viselkedésnek.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(42,NULL,8,'Önállóan hatékonyabban dolgozik, mint csapatban','{\"hu\":\"Önállóan hatékonyabban dolgozik, mint csapatban\"}','Önállóan hatékonyabban végzem a dolgom, mint csapatban','{\"hu\":\"Önállóan hatékonyabban végzem a dolgom, mint csapatban\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(43,NULL,8,'Önállóan képes megoldani a felmerülő problémákat, döntést hoz és felelősséget vállal.','{\"hu\":\"Önállóan képes megoldani a felmerülő problémákat, döntést hoz és felelősséget vállal.\"}','Önállóan megoldom a felmerülő problémákat, döntéseket hozok, melyekért felelősséget vállalok.','{\"hu\":\"Önállóan megoldom a felmerülő problémákat, döntéseket hozok, melyekért felelősséget vállalok.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(44,NULL,10,'Szereti a szakmai kihívásokat, szívesen oldja meg az új eseteket és sokat tanul belőlük.','{\"hu\":\"Szereti a szakmai kihívásokat, szívesen oldja meg az új eseteket és sokat tanul belőlük.\"}','Szeretem a szakmai kihívásokat és megoldásukból sokat tanulok.','{\"hu\":\"Szeretem a szakmai kihívásokat és megoldásukból sokat tanulok.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(45,NULL,10,'Ha egy szakmai problémára nem tudja azonnal a megoldást, tudja kihez fordulhat. Kiterjedt (akár cégen kívüli) kapcsolatrendszerrel rendelkezik.','{\"hu\":\"Ha egy szakmai problémára nem tudja azonnal a megoldást, tudja kihez fordulhat. Kiterjedt (akár cégen kívüli) kapcsolatrendszerrel rendelkezik.\"}','Ha egy szakmai problémára nem tudom a megoldást, tudom kit kell keresnem.','{\"hu\":\"Ha egy szakmai problémára nem tudom a megoldást, tudom kit kell keresnem.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz.','{\"hu\":\"Abszolút igaz.\"}',7,'hu','[\"hu\"]',NULL),
(46,NULL,3,'Fordulhatok hozzá, ha problémám van.','{\"hu\":\"Fordulhatok hozzá, ha problémám van.\"}','A kollégák nyugodtan fordulhatnak hozzám, bármilyen problémájuk van.','{\"hu\":\"A kollégák nyugodtan fordulhatnak hozzám, bármilyen problémájuk van.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(47,NULL,9,'Csapatban hatékonyabban dolgozik, mint önállóan.','{\"hu\":\"Csapatban hatékonyabban dolgozik, mint önállóan.\"}','Csapatban jobban megtalálom a helyem és hatékonyabb vagyok, mint önálló munka során.','{\"hu\":\"Csapatban jobban megtalálom a helyem és hatékonyabb vagyok, mint önálló munka során.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(48,NULL,6,'Az általa elvégzett munka hasznos a cég számára, melyet kiemelkedő eredménnyel végez.','{\"hu\":\"Az általa elvégzett munka hasznos a cég számára, melyet kiemelkedő eredménnyel végez.\"}','Hasznos munkát végzek a cégnél. Munkámat kiemelkedő eredményességgel végzem.','{\"hu\":\"Hasznos munkát végzek a cégnél. Munkámat kiemelkedő eredményességgel végzem.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(49,NULL,6,'Hiánya az egész céget hátrányosan érintené.','{\"hu\":\"Hiánya az egész céget hátrányosan érintené.\"}','Hiányom az egész céget hátrányosan érintené.','{\"hu\":\"Hiányom az egész céget hátrányosan érintené.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(50,NULL,4,'Büszke az elvégzett munkájára, a közösen elért eredményekre.','{\"hu\":\"Büszke az elvégzett munkájára, a közösen elért eredményekre.\"}','Büszke vagyok az elvégzett munkámra és a közösen elért eredményekre.','{\"hu\":\"Büszke vagyok az elvégzett munkámra és a közösen elért eredményekre.\"}','Egyáltalán nem igaz','{\"hu\":\"Egyáltalán nem igaz\"}','Abszolút igaz','{\"hu\":\"Abszolút igaz\"}',7,'hu','[\"hu\"]',NULL),
(51,1,14,'Ez egy tezst egyéni kérdés','{\"hu\":\"Ez egy tezst egyéni kérdés\"}','Ez egy teszt egyéni kérdés önértékeléshez','{\"hu\":\"Ez egy teszt egyéni kérdés önértékeléshez\"}','Legkevésbe','{\"hu\":\"Legkevésbe\"}','Leginkább','{\"hu\":\"Leginkább\"}',7,'hu','[\"hu\"]',NULL),
(52,8,16,'Teszt kérdés 1','{\"hu\":\"Teszt kérdés 1\",\"en\":\"Test question 3\"}','Önteszt kérdés 1','{\"hu\":\"Önteszt kérdés 1\",\"en\":\"self Test question 3\"}','Egyáltalán nem értek egyet','{\"hu\":\"Egyáltalán nem értek egyet\",\"en\":\"min\"}','Maximálisan egyet értek','{\"hu\":\"Maximálisan egyet értek\",\"en\":\"max\"}',7,'hu','[\"hu\",\"en\"]',NULL);
/*!40000 ALTER TABLE `competency_question` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_competency_question_bi` BEFORE INSERT ON `competency_question` FOR EACH ROW BEGIN
  DECLARE v_comp_org BIGINT UNSIGNED;
  SELECT organization_id INTO v_comp_org FROM competency WHERE id = NEW.competency_id;

  
  IF NEW.organization_id IS NULL AND v_comp_org IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Global question cannot reference org-specific competency';
  END IF;

  
  IF NEW.organization_id IS NOT NULL AND v_comp_org IS NOT NULL AND v_comp_org <> NEW.organization_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Question/competency org mismatch';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_competency_question_bu` BEFORE UPDATE ON `competency_question` FOR EACH ROW BEGIN
  DECLARE v_comp_org BIGINT UNSIGNED;
  SELECT organization_id INTO v_comp_org FROM competency WHERE id = NEW.competency_id;
  IF NEW.organization_id IS NULL AND v_comp_org IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Global question cannot reference org-specific competency';
  END IF;
  IF NEW.organization_id IS NOT NULL AND v_comp_org IS NOT NULL AND v_comp_org <> NEW.organization_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Question/competency org mismatch';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `competency_submit`
--

DROP TABLE IF EXISTS `competency_submit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `competency_submit` (
  `assessment_id` bigint(20) unsigned NOT NULL,
  `competency_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `value` smallint(6) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  UNIQUE KEY `uq_comp_submit` (`assessment_id`,`competency_id`,`user_id`,`target_id`,`type`),
  KEY `competency_submit_fk1_idx` (`assessment_id`),
  KEY `competency_submit_fk2_idx` (`competency_id`),
  KEY `competency_submit_fk3_idx` (`user_id`),
  KEY `competency_submit_fk4_idx` (`target_id`),
  KEY `idx_comp_submit_assessment_a1` (`assessment_id`),
  KEY `idx_comp_submit_assessment_target` (`assessment_id`,`target_id`),
  CONSTRAINT `competency_submit_fk1` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `competency_submit_fk2` FOREIGN KEY (`competency_id`) REFERENCES `competency` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `competency_submit_fk3` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `competency_submit_fk4` FOREIGN KEY (`target_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competency_submit`
--

LOCK TABLES `competency_submit` WRITE;
/*!40000 ALTER TABLE `competency_submit` DISABLE KEYS */;
INSERT INTO `competency_submit` VALUES
(6,1,33,33,86,'self'),
(6,4,33,33,73,'self'),
(6,5,33,33,73,'self'),
(6,6,33,33,54,'self'),
(6,7,33,33,81,'self'),
(6,10,33,33,92,'self'),
(7,2,33,33,92,'self'),
(7,3,33,33,91,'self'),
(7,4,33,33,93,'self'),
(7,5,33,33,90,'self'),
(7,6,33,33,79,'self'),
(7,7,33,33,92,'self'),
(7,10,33,33,93,'self'),
(8,1,57,55,63,'ceo'),
(8,2,57,55,41,'ceo'),
(8,3,57,55,62,'ceo'),
(8,3,57,58,78,'ceo'),
(8,4,57,56,88,'ceo'),
(8,5,57,57,86,'self'),
(8,6,43,43,97,'self'),
(8,6,57,43,64,'ceo'),
(8,6,57,58,64,'ceo'),
(8,7,57,56,73,'ceo'),
(8,7,57,57,71,'self'),
(8,8,57,58,57,'ceo'),
(8,9,43,43,95,'self'),
(8,9,57,43,54,'ceo'),
(8,10,57,56,59,'ceo'),
(8,14,57,57,86,'self'),
(8,14,57,58,86,'ceo'),
(9,1,43,55,70,'colleague'),
(9,1,57,55,77,'ceo'),
(9,2,43,55,80,'colleague'),
(9,2,57,55,76,'ceo'),
(9,3,43,55,82,'colleague'),
(9,3,57,55,78,'ceo'),
(9,3,57,58,84,'ceo'),
(9,4,43,56,70,'colleague'),
(9,4,57,56,90,'ceo'),
(9,5,57,57,73,'self'),
(9,6,43,43,90,'self'),
(9,6,57,43,79,'ceo'),
(9,6,57,58,83,'ceo'),
(9,7,43,56,85,'colleague'),
(9,7,57,56,77,'ceo'),
(9,7,57,57,77,'self'),
(9,8,57,58,93,'ceo'),
(9,9,43,43,80,'self'),
(9,9,57,43,76,'ceo'),
(9,10,43,56,77,'colleague'),
(9,10,57,56,66,'ceo'),
(9,14,57,57,71,'self'),
(9,14,57,58,86,'ceo'),
(13,6,43,43,79,'self'),
(13,9,43,43,73,'self'),
(13,1,43,55,75,'colleague'),
(13,2,43,55,80,'colleague'),
(13,3,43,55,80,'colleague'),
(13,4,43,56,67,'colleague'),
(13,7,43,56,70,'colleague'),
(13,10,43,56,99,'colleague'),
(13,5,57,57,83,'self'),
(13,7,57,57,65,'self'),
(13,14,57,57,43,'self'),
(13,1,57,55,77,'ceo'),
(13,2,57,55,89,'ceo'),
(13,3,57,55,67,'ceo'),
(13,6,57,43,61,'ceo'),
(13,9,57,43,80,'ceo'),
(13,3,57,58,82,'ceo'),
(13,6,57,58,71,'ceo'),
(13,8,57,58,83,'ceo'),
(13,14,57,58,57,'ceo'),
(13,4,57,56,67,'ceo'),
(13,7,57,56,70,'ceo'),
(13,10,57,56,70,'ceo'),
(17,1,59,59,92,'self'),
(17,4,59,59,75,'self'),
(17,6,59,59,93,'self'),
(17,7,59,59,92,'self');
/*!40000 ALTER TABLE `competency_submit` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_competency_submit_bi` BEFORE INSERT ON `competency_submit` FOR EACH ROW BEGIN
  DECLARE v_org BIGINT UNSIGNED;
  DECLARE v_comp_org BIGINT UNSIGNED;

  SELECT organization_id INTO v_org
  FROM assessment WHERE id = NEW.assessment_id;

  SELECT organization_id INTO v_comp_org
  FROM competency WHERE id = NEW.competency_id;

  IF v_org IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Assessment has no organization';
  END IF;

  
  IF NEW.user_id IS NOT NULL AND NOT is_org_member(v_org, NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='user_id not org member';
  END IF;

  IF NOT is_org_member(v_org, NEW.target_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='target_id not org member';
  END IF;

  IF v_comp_org IS NOT NULL AND v_comp_org <> v_org THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Competency belongs to a different organization';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_competency_submit_bu` BEFORE UPDATE ON `competency_submit` FOR EACH ROW BEGIN
  DECLARE v_org BIGINT UNSIGNED;
  DECLARE v_comp_org BIGINT UNSIGNED;

  SELECT organization_id INTO v_org
  FROM assessment WHERE id = NEW.assessment_id;

  SELECT organization_id INTO v_comp_org
  FROM competency WHERE id = NEW.competency_id;

  IF v_org IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Assessment has no organization';
  END IF;

  
  IF NEW.user_id IS NOT NULL AND NOT is_org_member(v_org, NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='user_id not org member';
  END IF;

  IF NOT is_org_member(v_org, NEW.target_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='target_id not org member';
  END IF;

  IF v_comp_org IS NOT NULL AND v_comp_org <> v_org THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Competency belongs to a different organization';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `config` (
  `name` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config`
--

LOCK TABLES `config` WRITE;
/*!40000 ALTER TABLE `config` DISABLE KEYS */;
INSERT INTO `config` VALUES
('monthly_level_down','70'),
('normal_level_down','70'),
('normal_level_up','85');
/*!40000 ALTER TABLE `config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `connection_question`
--

DROP TABLE IF EXISTS `connection_question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `connection_question` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(1024) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `connection_question`
--

LOCK TABLES `connection_question` WRITE;
/*!40000 ALTER TABLE `connection_question` DISABLE KEYS */;
/*!40000 ALTER TABLE `connection_question` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `connection_survey`
--

DROP TABLE IF EXISTS `connection_survey`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `connection_survey` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `started_at` datetime NOT NULL,
  `due_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_connection_survey_org` (`organization_id`),
  CONSTRAINT `fk_connection_survey_org` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `connection_survey`
--

LOCK TABLES `connection_survey` WRITE;
/*!40000 ALTER TABLE `connection_survey` DISABLE KEYS */;
/*!40000 ALTER TABLE `connection_survey` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cookie_consents`
--

DROP TABLE IF EXISTS `cookie_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cookie_consents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `necessary` tinyint(1) NOT NULL DEFAULT 1,
  `analytics` tinyint(1) NOT NULL DEFAULT 0,
  `marketing` tinyint(1) NOT NULL DEFAULT 0,
  `preferences` tinyint(1) NOT NULL DEFAULT 0,
  `consent_date` timestamp NOT NULL,
  `consent_version` varchar(10) NOT NULL DEFAULT '1.0',
  `user_agent` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`user_agent`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cookie_consents_session_id_created_at_index` (`session_id`,`created_at`),
  KEY `cookie_consents_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `cookie_consents_user_id_foreign` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cookie_consents`
--

LOCK TABLES `cookie_consents` WRITE;
/*!40000 ALTER TABLE `cookie_consents` DISABLE KEYS */;
INSERT INTO `cookie_consents` VALUES
(3,'GVfwpfSDBEAQvo5rJRGwOTtBPMzpatLD1zwKrNHu',NULL,'85.66.68.150',1,1,0,0,'2025-09-20 13:42:35','1.0','{\"browser\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Mobile Safari\\/537.36\",\"platform\":\"\\\"Android\\\"\"}','2025-09-20 13:42:35','2025-09-20 13:42:35'),
(4,'BqQ0dOjH1eP5dFVFC6N6lIMJbfd5CNfA6vRy8XRN',NULL,'85.66.68.150',1,0,0,0,'2025-09-20 14:13:29','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-20 14:13:29','2025-09-20 14:13:29'),
(5,'YAnGUyF45NSCRGPUVITHUdU2APtzlavl89cE3Sgf',NULL,'85.66.68.150',1,1,0,0,'2025-09-20 14:16:33','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-20 14:16:33','2025-09-20 14:16:33'),
(6,'YAnGUyF45NSCRGPUVITHUdU2APtzlavl89cE3Sgf',NULL,'85.66.68.150',1,0,0,0,'2025-09-20 14:16:41','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-20 14:16:41','2025-09-20 14:16:41'),
(7,'YAnGUyF45NSCRGPUVITHUdU2APtzlavl89cE3Sgf',NULL,'85.66.68.150',1,1,0,0,'2025-09-20 14:39:17','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-20 14:39:17','2025-09-20 14:39:17'),
(8,'gfv4OXsW2IVUUMcMfPcDWhvURfMpRliYa65QmRAV',NULL,'85.66.67.75',1,0,0,0,'2025-09-20 17:15:44','1.0','{\"browser\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Mobile Safari\\/537.36\",\"platform\":\"\\\"Android\\\"\"}','2025-09-20 17:15:44','2025-09-20 17:15:44'),
(9,'6PlQRJDPY1XxJ8JxVYQnGJkspRsC2YsR9SpvOM1g',NULL,'85.66.67.75',1,1,0,0,'2025-09-21 06:24:54','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-21 06:24:54','2025-09-21 06:24:54'),
(10,'0i2UKgBl2p58XiAaVD3jBy9WKk4x0qnShThHyA6I',NULL,'85.66.67.75',1,1,0,0,'2025-09-21 06:54:19','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-21 06:54:19','2025-09-21 06:54:19'),
(11,'671VUvWxJIJm3SZKq4PUqJPYFWDFo9znhrPuZ1pe',NULL,'85.66.67.75',1,1,0,0,'2025-09-21 07:00:54','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-21 07:00:54','2025-09-21 07:00:54'),
(12,'kLPu2zviAfkOk4HYamMPpb3oK7iEkyE2hGFEsr3T',NULL,'85.66.68.150',1,1,0,0,'2025-09-23 19:55:31','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-23 19:55:31','2025-09-23 19:55:31'),
(13,'fcnhTPK5qT9OHoWTUn99GTAYiXHHmIEcInXs1Ofc',NULL,'85.66.68.150',1,1,0,0,'2025-09-26 13:20:44','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-26 13:20:44','2025-09-26 13:20:44'),
(14,'3ynzipXDOaW6ftFL4wrRhDkrrS4nRftQ6uK62svP',NULL,'85.66.68.150',1,0,0,0,'2025-09-26 13:46:58','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-26 13:46:58','2025-09-26 13:46:58'),
(15,'l48yzTu2DDBFTzRsNALi71Xu4De30Dabbc29Yheo',NULL,'85.66.68.150',1,0,0,0,'2025-09-26 14:00:16','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-26 14:00:16','2025-09-26 14:00:16'),
(16,'lSbofWpCRE7e6tUxeOlCyeJ7iEIgvyYlPkCo0FXv',NULL,'85.66.68.150',1,1,0,0,'2025-09-26 14:01:06','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-26 14:01:06','2025-09-26 14:01:06'),
(17,'2rsVC5Gj0gleecTjSeaZM3TfcvUvhm6MOym5hNHv',NULL,'85.66.68.150',1,1,0,0,'2025-09-27 16:33:35','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-27 16:33:35','2025-09-27 16:33:35'),
(18,'1ZR94VTX6i2iotKxD8fWq01CoIQ0xfWK0Kp2eDOk',NULL,'2001:4c4e:1852:af00:4118:8e86:66bb:3c85',1,1,0,0,'2025-09-27 19:51:12','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-27 19:51:12','2025-09-27 19:51:12'),
(19,'sWL9u5wZGHTLsQN1GkDIifssMv12F3WCTz9hstof',59,'2001:4c4e:1852:af00:4118:8e86:66bb:3c85',1,1,0,0,'2025-09-27 19:58:28','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-27 19:58:28','2025-09-27 19:58:28'),
(20,'YmaQ5jrWPy1TMn8D4KWA18NjwgnRxJPknBRY7ACa',NULL,'2001:4c4e:1852:af00:4118:8e86:66bb:3c85',1,1,0,0,'2025-09-27 20:00:42','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-09-27 20:00:42','2025-09-27 20:00:42'),
(21,'IDvNoPccRScR8Hu7PYG2XJ9R8rRrtQUOSxYXSMpb',NULL,'91.120.114.103',1,1,0,0,'2025-10-01 09:21:48','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-01 09:21:48','2025-10-01 09:21:48'),
(22,'Ui1fsOK1VnUWFCWF7TqWQkE6bWpipTpzM29GuRCC',NULL,'85.66.68.150',1,1,0,0,'2025-10-02 17:06:45','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-02 17:06:45','2025-10-02 17:06:45'),
(23,'NwBkp9eAnif8C0nCVz2VJ1QGbN4PuN2tQqzlnQG5',1,'85.66.68.150',1,1,0,0,'2025-10-02 20:19:23','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-02 20:19:23','2025-10-02 20:19:23'),
(24,'NwBkp9eAnif8C0nCVz2VJ1QGbN4PuN2tQqzlnQG5',1,'85.66.68.150',1,0,0,0,'2025-10-02 20:19:30','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-02 20:19:30','2025-10-02 20:19:30'),
(25,'QxKDzIbogeEtH2m8ANLpcvtERtBVk4gnV5CvZMvr',NULL,'85.66.68.150',1,1,0,0,'2025-10-04 15:59:58','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-04 15:59:58','2025-10-04 15:59:58'),
(26,'bvwM0Cz7TzmZCTgXxvbwMZFEgd96Go2Z4PrCD7iz',NULL,'85.66.68.150',1,1,0,0,'2025-10-04 18:15:50','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-04 18:15:50','2025-10-04 18:15:50'),
(27,'4fnyjyDtBYrD3aH7NBzWcyD6EIwWoz6tbpkHa003',NULL,'85.66.68.150',1,1,0,0,'2025-10-05 07:17:05','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-05 07:17:05','2025-10-05 07:17:05'),
(28,'uPx7YtBLWn6cixygheUMvhpQuRuBgAJS07m081BZ',NULL,'85.66.68.150',1,1,0,0,'2025-10-05 19:45:32','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-05 19:45:32','2025-10-05 19:45:32'),
(29,'rTdFNKy3Uq1u2fKgJoGWjVDfE4uZboVVy5OsttBP',NULL,'85.66.68.150',1,1,0,0,'2025-10-06 18:25:12','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-06 18:25:12','2025-10-06 18:25:12'),
(30,'9Zuy6rU0GxjhuOn9NkufmHjTPBHUxoXnBh9n3Aqw',NULL,'85.66.68.150',1,1,0,0,'2025-10-13 11:15:48','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/141.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-13 11:15:48','2025-10-13 11:15:48'),
(31,'fgB0oXhFuZrYRQkW7zD9kQ5wK1PcivHruFlBtznC',NULL,'85.66.68.150',1,1,0,0,'2025-10-13 17:25:00','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/141.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-13 17:25:00','2025-10-13 17:25:00'),
(32,'b5kVhWtnzr7IaVKqsBSTtPNx8N5Otkx7ZAMdELGM',NULL,'188.6.37.84',1,1,0,0,'2025-10-14 08:27:37','1.0','{\"browser\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/141.0.0.0 Mobile Safari\\/537.36\",\"platform\":\"\\\"Android\\\"\"}','2025-10-14 08:27:37','2025-10-14 08:27:37'),
(33,'bVwvg1u6jXa1k0MpA67rVlAw8w1TWYT6lVXl2OEf',NULL,'188.6.37.84',1,1,0,0,'2025-10-15 07:32:50','1.0','{\"browser\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/140.0.0.0 Safari\\/537.36\",\"platform\":\"\\\"Windows\\\"\"}','2025-10-15 07:32:50','2025-10-15 07:32:50');
/*!40000 ALTER TABLE `cookie_consents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_batch_jobs`
--

DROP TABLE IF EXISTS `email_batch_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_batch_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_job_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `batch_type` enum('welcome','password_setup','password_reset','bulk') NOT NULL DEFAULT 'welcome',
  `user_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`user_ids`)),
  `total_emails` int(11) NOT NULL DEFAULT 0,
  `sent_emails` int(11) NOT NULL DEFAULT 0,
  `failed_emails` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `error_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`error_log`)),
  `delay_seconds` int(11) NOT NULL DEFAULT 30,
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_import_job` (`import_job_id`),
  KEY `idx_email_org` (`organization_id`),
  KEY `idx_email_status` (`status`),
  KEY `idx_email_scheduled` (`scheduled_at`),
  KEY `idx_email_type` (`batch_type`),
  CONSTRAINT `fk_email_import_job` FOREIGN KEY (`import_job_id`) REFERENCES `user_import_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_email_org` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_batch_jobs`
--

LOCK TABLES `email_batch_jobs` WRITE;
/*!40000 ALTER TABLE `email_batch_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_batch_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verification_codes`
--

DROP TABLE IF EXISTS `email_verification_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `code` varchar(6) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_verification_codes_email_session_used_unique` (`email`,`session_id`,`used_at`),
  KEY `email_verification_codes_email_index` (`email`),
  KEY `email_verification_codes_session_id_index` (`session_id`),
  KEY `email_verification_codes_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_verification_codes`
--

LOCK TABLES `email_verification_codes` WRITE;
/*!40000 ALTER TABLE `email_verification_codes` DISABLE KEYS */;
INSERT INTO `email_verification_codes` VALUES
(1,'nwbusinesshu@gmail.com','306923','0i2UKgBl2p58XiAaVD3jBy9WKk4x0qnShThHyA6I','2025-09-21 07:04:10','2025-09-21 06:54:49','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-21 06:54:10','2025-09-21 06:54:49'),
(2,'nwbusinesshu@gmail.com','405893','671VUvWxJIJm3SZKq4PUqJPYFWDFo9znhrPuZ1pe','2025-09-21 07:10:57','2025-09-21 07:01:40','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-21 07:00:57','2025-09-21 07:01:40'),
(3,'nwbusinesshu@gmail.com','539826','671VUvWxJIJm3SZKq4PUqJPYFWDFo9znhrPuZ1pe','2025-09-21 07:11:40','2025-09-21 07:02:16','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-21 07:01:40','2025-09-21 07:02:16'),
(4,'nwbusinesshu@gmail.com','599240','671VUvWxJIJm3SZKq4PUqJPYFWDFo9znhrPuZ1pe','2025-09-21 07:12:24','2025-09-21 07:02:38','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-21 07:02:24','2025-09-21 07:02:38'),
(5,'nwbusinesshu@gmail.com','390122','671VUvWxJIJm3SZKq4PUqJPYFWDFo9znhrPuZ1pe','2025-09-21 07:13:37','2025-09-21 07:03:55','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-21 07:03:37','2025-09-21 07:03:55'),
(6,'nwbusinesshu@gmail.com','710717','ySaaQXgjxS0a0pjBYp8kGE4CD26BINpLGsLNpKfH','2025-09-23 20:50:59','2025-09-23 20:41:11','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-23 20:40:59','2025-09-23 20:41:11'),
(7,'nwbusinesshu@gmail.com','882875','ZPLtTLTUX7t3ys3awLy5lFys1bOhkyEFZvsp1vzM','2025-09-26 13:05:42',NULL,'85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-26 12:55:42','2025-09-26 12:55:42'),
(8,'gallo@nwbusiness.hu','317938','2rsVC5Gj0gleecTjSeaZM3TfcvUvhm6MOym5hNHv','2025-09-27 16:43:45','2025-09-27 16:34:07','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-27 16:33:45','2025-09-27 16:34:07'),
(9,'tesztmanager@nwbusiness.hu','177941','6xgwXVE7Vwu2ljtWP1CHmcIh8hxXZwv4cAC3Nccy','2025-09-27 16:46:28','2025-09-27 16:36:39','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-27 16:36:28','2025-09-27 16:36:39'),
(10,'nwbusinesshu@gmail.com','295680','1ZR94VTX6i2iotKxD8fWq01CoIQ0xfWK0Kp2eDOk','2025-09-27 20:02:14',NULL,'2001:4c4e:1852:af00:4118:8e86:66bb:3c85','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-27 19:52:14','2025-09-27 19:52:14'),
(11,'gallo@nwbusiness.hu','379489','63LktDuCGQtqCQCatLPlmNPVOJDeKPyVAoKjOaNr','2025-09-27 20:08:10','2025-09-27 19:58:24','2001:4c4e:1852:af00:4118:8e86:66bb:3c85','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-27 19:58:10','2025-09-27 19:58:24'),
(12,'nwbusinesshu@gmail.com','318485','7RTUrn9cgLwGHzVDD1aw1xyJoiGuAFoiwYquM80t','2025-09-29 14:49:09',NULL,'85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-09-29 14:39:09','2025-09-29 14:39:09'),
(13,'scorekingshu@gmail.com','671679','QxKDzIbogeEtH2m8ANLpcvtERtBVk4gnV5CvZMvr','2025-10-04 18:09:39','2025-10-04 17:59:59','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-04 17:59:39','2025-10-04 17:59:59'),
(14,'tesztelek2@nwbusiness.hu','665511','EMKJy2xajv4riZZaJ7YZyhcptZsuF71gb30Z6wsH','2025-10-05 08:21:28','2025-10-05 08:11:42','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-05 08:11:28','2025-10-05 08:11:42'),
(15,'nwbusinesshu@gmail.com','163478','1C7M8rhlZcuWYipKTn0w6oK33AZRxrPeLmoDLvQy','2025-10-05 21:34:12',NULL,'85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-05 21:24:12','2025-10-05 21:24:12'),
(16,'tesztelek2@nwbusiness.hu','327275','3NDxlAy3T4uXdGbfUo2Ia4fwruOF6JjwwZ1YTE3m','2025-10-05 23:12:35','2025-10-05 23:02:48','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-05 23:02:35','2025-10-05 23:02:48'),
(17,'gaben@nwbusiness.hu','967697','rTdFNKy3Uq1u2fKgJoGWjVDfE4uZboVVy5OsttBP','2025-10-06 18:35:07','2025-10-06 18:25:47','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-06 18:25:07','2025-10-06 18:25:47'),
(18,'gaben@nwbusiness.hu','434040','fgB0oXhFuZrYRQkW7zD9kQ5wK1PcivHruFlBtznC','2025-10-13 18:19:35','2025-10-13 18:09:50','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-13 18:09:35','2025-10-13 18:09:50');
/*!40000 ALTER TABLE `email_verification_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `help_chat_messages`
--

DROP TABLE IF EXISTS `help_chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `help_chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) unsigned NOT NULL,
  `role` enum('user','assistant') NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `help_chat_messages_session_id_created_at_index` (`session_id`,`created_at`),
  CONSTRAINT `help_chat_messages_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `help_chat_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `help_chat_messages`
--

LOCK TABLES `help_chat_messages` WRITE;
/*!40000 ALTER TABLE `help_chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `help_chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `help_chat_sessions`
--

DROP TABLE IF EXISTS `help_chat_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `help_chat_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'New Conversation',
  `view_key` varchar(100) DEFAULT NULL,
  `locale` varchar(10) NOT NULL DEFAULT 'hu',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `help_chat_sessions_user_id_last_message_at_index` (`user_id`,`last_message_at`),
  CONSTRAINT `help_chat_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `help_chat_sessions`
--

LOCK TABLES `help_chat_sessions` WRITE;
/*!40000 ALTER TABLE `help_chat_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `help_chat_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `failed_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_attempts_email_ip_address_index` (`email`,`ip_address`),
  KEY `login_attempts_email_locked_until_index` (`email`,`locked_until`),
  KEY `login_attempts_email_index` (`email`),
  KEY `login_attempts_ip_address_index` (`ip_address`),
  KEY `login_attempts_locked_until_index` (`locked_until`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'2014_10_12_000000_create_users_table',1),
(2,'2014_10_12_100000_create_password_resets_table',1),
(3,'2019_08_19_000000_create_failed_jobs_table',1),
(4,'2019_12_14_000001_create_personal_access_tokens_table',1),
(5,'2025_08_21_121056_create_organization_profiles_table',2),
(6,'2025_09_21_104130_add_multilang_support_to_competencies',3),
(7,'2025_10_06_120000_create_webhook_events_table',4),
(8,'2025_10_06_150000_create_login_attempts_table',5),
(9,'2025_10_08_000001_create_bonus_system_tables',6);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization`
--

DROP TABLE IF EXISTS `organization`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `removed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization`
--

LOCK TABLES `organization` WRITE;
/*!40000 ALTER TABLE `organization` DISABLE KEYS */;
INSERT INTO `organization` VALUES
(1,'Pilot Kft.','pilot','2025-08-18 22:29:52',NULL),
(2,'Teszt Cég',NULL,'2025-08-20 13:29:51','2025-08-20 18:16:07'),
(3,'Pilot 2 Kft.',NULL,'2025-08-20 14:03:45','2025-08-21 11:09:46'),
(4,'Pilot 3 Kft',NULL,'2025-08-21 11:10:07','2025-08-21 11:13:02'),
(5,'Pilot 3 Kft',NULL,'2025-08-21 11:10:07',NULL),
(6,'Pilot 4 Kft',NULL,'2025-08-21 11:20:26','2025-08-27 09:59:23'),
(7,'Pilot 4 Kft',NULL,'2025-08-21 11:20:26','2025-08-27 09:59:16'),
(8,'Pilot 5 Kft',NULL,'2025-08-21 12:38:21',NULL),
(14,'GP AUTÓ Kft.',NULL,'2025-09-20 16:46:42',NULL),
(21,'Payment4uKft.',NULL,'2025-10-04 19:58:40',NULL),
(22,'Pilot 9 Kft',NULL,'2025-10-04 20:16:21',NULL),
(23,'Pilot 10 Kft.',NULL,'2025-10-05 09:31:02',NULL),
(24,'Pilot 11 Kft',NULL,'2025-10-05 09:46:20',NULL);
/*!40000 ALTER TABLE `organization` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_config`
--

DROP TABLE IF EXISTS `organization_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_config` (
  `organization_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`organization_id`,`name`),
  CONSTRAINT `org_cfg_fk1` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_config`
--

LOCK TABLES `organization_config` WRITE;
/*!40000 ALTER TABLE `organization_config` DISABLE KEYS */;
INSERT INTO `organization_config` VALUES
(1,'ai_telemetry_enabled','1'),
(1,'easy_relation_setup','1'),
(1,'employees_see_bonuses','1'),
(1,'enable_bonus_calculation','1'),
(1,'enable_multi_level','1'),
(1,'force_oauth_2fa','0'),
(1,'monthly_level_down','70'),
(1,'never_below_abs_min_for_promo','70'),
(1,'no_forced_demotion_if_high_cohesion','0'),
(1,'normal_level_down','70'),
(1,'normal_level_up','85'),
(1,'show_bonus_malus','1'),
(1,'strict_anonymous_mode','0'),
(1,'target_demotion_rate_max','0.3'),
(1,'target_promo_rate_max','0.3'),
(1,'threshold_bottom_pct','20'),
(1,'threshold_gap_min','2'),
(1,'threshold_grace_points','5'),
(1,'threshold_min_abs_up','70'),
(1,'threshold_mode','suggested'),
(1,'threshold_top_pct','15'),
(1,'translation_languages','[\"hu\",\"en\"]'),
(1,'use_telemetry_trust','1'),
(2,'enable_multi_level','0'),
(2,'threshold_bottom_pct','20'),
(2,'threshold_min_abs_up','80'),
(2,'threshold_mode','fixed'),
(2,'threshold_top_pct','15'),
(3,'enable_multi_level','0'),
(3,'threshold_bottom_pct','20'),
(3,'threshold_min_abs_up','80'),
(3,'threshold_mode','fixed'),
(3,'threshold_top_pct','15'),
(4,'enable_multi_level','0'),
(4,'threshold_bottom_pct','20'),
(4,'threshold_min_abs_up','80'),
(4,'threshold_mode','fixed'),
(4,'threshold_top_pct','15'),
(5,'ai_telemetry_enabled','1'),
(5,'enable_multi_level','0'),
(5,'never_below_abs_min_for_promo',''),
(5,'no_forced_demotion_if_high_cohesion','1'),
(5,'normal_level_down','75'),
(5,'normal_level_up','90'),
(5,'show_bonus_malus','1'),
(5,'strict_anonymous_mode','0'),
(5,'target_demotion_rate_max','0.1'),
(5,'target_promo_rate_max','0.2'),
(5,'threshold_bottom_pct','20'),
(5,'threshold_gap_min','2'),
(5,'threshold_grace_points','5'),
(5,'threshold_min_abs_up','80'),
(5,'threshold_mode','fixed'),
(5,'threshold_top_pct','15'),
(5,'use_telemetry_trust','1'),
(6,'enable_multi_level','0'),
(6,'threshold_bottom_pct','20'),
(6,'threshold_min_abs_up','80'),
(6,'threshold_mode','fixed'),
(6,'threshold_top_pct','15'),
(7,'enable_multi_level','0'),
(7,'threshold_bottom_pct','20'),
(7,'threshold_min_abs_up','80'),
(7,'threshold_mode','fixed'),
(7,'threshold_top_pct','15'),
(8,'ai_telemetry_enabled','1'),
(8,'enable_multi_level','0'),
(8,'strict_anonymous_mode','0'),
(8,'threshold_bottom_pct','20'),
(8,'threshold_min_abs_up','80'),
(8,'threshold_mode','fixed'),
(8,'threshold_top_pct','15'),
(8,'translation_languages','[\"hu\",\"en\"]'),
(14,'ai_telemetry_enabled','1'),
(14,'enable_multi_level','0'),
(14,'show_bonus_malus','1'),
(14,'translation_languages','[\"hu\",\"en\"]'),
(21,'ai_telemetry_enabled','1'),
(21,'enable_multi_level','0'),
(21,'show_bonus_malus','1'),
(22,'ai_telemetry_enabled','1'),
(22,'enable_multi_level','0'),
(22,'show_bonus_malus','1'),
(23,'ai_telemetry_enabled','1'),
(23,'enable_multi_level','0'),
(23,'never_below_abs_min_for_promo',''),
(23,'no_forced_demotion_if_high_cohesion','1'),
(23,'normal_level_down','70'),
(23,'normal_level_up','85'),
(23,'show_bonus_malus','1'),
(23,'target_demotion_rate_max','0.1'),
(23,'target_promo_rate_max','0.2'),
(23,'threshold_bottom_pct','20'),
(23,'threshold_gap_min','2'),
(23,'threshold_grace_points','5'),
(23,'threshold_min_abs_up','80'),
(23,'threshold_mode','fixed'),
(23,'threshold_top_pct','15'),
(23,'translation_languages','[\"hu\",\"de\"]'),
(23,'use_telemetry_trust','1'),
(24,'ai_telemetry_enabled','1'),
(24,'enable_multi_level','0'),
(24,'show_bonus_malus','1');
/*!40000 ALTER TABLE `organization_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_department_managers`
--

DROP TABLE IF EXISTS `organization_department_managers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_department_managers` (
  `organization_id` bigint(20) unsigned NOT NULL,
  `department_id` bigint(20) unsigned NOT NULL,
  `manager_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`organization_id`,`department_id`,`manager_id`),
  KEY `idx_org_dept` (`organization_id`,`department_id`),
  KEY `idx_org_mgr` (`organization_id`,`manager_id`),
  KEY `fk_dept_mgr_dept` (`department_id`),
  KEY `fk_dept_mgr_user` (`manager_id`),
  CONSTRAINT `fk_dept_mgr_dept` FOREIGN KEY (`department_id`) REFERENCES `organization_departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dept_mgr_org` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dept_mgr_user` FOREIGN KEY (`manager_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_department_managers`
--

LOCK TABLES `organization_department_managers` WRITE;
/*!40000 ALTER TABLE `organization_department_managers` DISABLE KEYS */;
INSERT INTO `organization_department_managers` VALUES
(1,2,70,'2025-10-06 13:37:36',NULL),
(5,1,60,'2025-09-15 15:14:51',NULL),
(5,1,61,'2025-09-15 15:14:51',NULL);
/*!40000 ALTER TABLE `organization_department_managers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_departments`
--

DROP TABLE IF EXISTS `organization_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `removed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_org` (`organization_id`),
  CONSTRAINT `fk_org_dept_org` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_departments`
--

LOCK TABLES `organization_departments` WRITE;
/*!40000 ALTER TABLE `organization_departments` DISABLE KEYS */;
INSERT INTO `organization_departments` VALUES
(1,5,'Pénzügy','2025-09-11 10:19:55',NULL),
(2,1,'Értékesítés','2025-09-27 21:46:38',NULL);
/*!40000 ALTER TABLE `organization_departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_profiles`
--

DROP TABLE IF EXISTS `organization_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `tax_number` varchar(191) DEFAULT NULL,
  `eu_vat_number` varchar(32) DEFAULT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'HU',
  `postal_code` varchar(16) DEFAULT NULL,
  `region` varchar(64) DEFAULT NULL,
  `city` varchar(64) DEFAULT NULL,
  `street` varchar(128) DEFAULT NULL,
  `house_number` varchar(32) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `employee_limit` int(10) unsigned DEFAULT NULL,
  `subscription_type` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `org_profiles_org_id_fk` (`organization_id`),
  KEY `idx_tax_number` (`tax_number`),
  KEY `idx_eu_vat_number` (`eu_vat_number`),
  CONSTRAINT `org_profiles_org_id_fk` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_profiles`
--

LOCK TABLES `organization_profiles` WRITE;
/*!40000 ALTER TABLE `organization_profiles` DISABLE KEYS */;
INSERT INTO `organization_profiles` VALUES
(1,8,'12345678',NULL,'HU',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'free','2025-08-21 10:38:21','2025-08-21 10:47:38'),
(2,1,'12345678',NULL,'HU',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'free','2025-08-22 09:34:21','2025-08-22 09:34:21'),
(3,5,'32576340-2-03',NULL,'HU','6524',NULL,'Dávod','Dózsa György utca','64',NULL,NULL,'pro','2025-09-16 18:59:35','2025-09-16 18:59:35'),
(9,14,'32576340-2-03','','HU',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pro','2025-09-20 14:46:42','2025-09-26 09:45:42'),
(16,21,'32576340-2-03',NULL,'HU',NULL,NULL,NULL,NULL,NULL,NULL,15,NULL,'2025-10-04 17:58:40','2025-10-04 17:58:40'),
(17,22,'32576340-2-03',NULL,'HU',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pro','2025-10-04 18:16:21','2025-10-04 18:16:21'),
(18,23,'32576341-2-03',NULL,'HU',NULL,NULL,NULL,NULL,NULL,NULL,15,NULL,'2025-10-05 07:31:02','2025-10-05 07:31:02'),
(19,24,'32576342-2-03','','HU','6524',NULL,'Dávod','Dózsa u.','64','+36702652651',15,'pro','2025-10-05 07:46:20','2025-10-05 07:46:20');
/*!40000 ALTER TABLE `organization_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_user`
--

DROP TABLE IF EXISTS `organization_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_user` (
  `organization_id` bigint(20) unsigned NOT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `role` enum('owner','admin','manager','employee') NOT NULL DEFAULT 'employee',
  PRIMARY KEY (`organization_id`,`user_id`),
  KEY `org_user_fk2` (`user_id`),
  KEY `idx_orguser_department` (`department_id`),
  CONSTRAINT `fk_orguser_department` FOREIGN KEY (`department_id`) REFERENCES `organization_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `org_user_fk1` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`),
  CONSTRAINT `org_user_fk2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_user`
--

LOCK TABLES `organization_user` WRITE;
/*!40000 ALTER TABLE `organization_user` DISABLE KEYS */;
INSERT INTO `organization_user` VALUES
(1,2,43,NULL,'employee'),
(1,NULL,44,NULL,'admin'),
(1,NULL,55,NULL,'employee'),
(1,2,56,NULL,'employee'),
(1,NULL,57,NULL,'employee'),
(1,2,58,NULL,'employee'),
(1,NULL,70,'értékesitési vezető','employee'),
(1,2,82,'takarító','employee'),
(1,NULL,83,'zeneszerző','employee'),
(1,NULL,84,'marketing specialist','employee'),
(1,NULL,92,'tesztasd','employee'),
(5,1,59,'teszt','employee'),
(5,NULL,60,'könyvelő','employee'),
(5,NULL,61,NULL,'employee'),
(5,NULL,62,'teszt','employee'),
(5,NULL,63,'basszameg','employee'),
(6,NULL,37,NULL,'admin'),
(8,NULL,42,NULL,'admin'),
(14,NULL,69,NULL,'admin'),
(21,NULL,77,NULL,'admin'),
(22,NULL,78,NULL,'admin'),
(23,NULL,79,NULL,'admin'),
(24,NULL,80,NULL,'admin');
/*!40000 ALTER TABLE `organization_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_setup`
--

DROP TABLE IF EXISTS `password_setup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_setup` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash_unique` (`token_hash`),
  KEY `org_user_open` (`organization_id`,`user_id`,`used_at`),
  KEY `fk_ps_user` (`user_id`),
  KEY `fk_ps_created_by` (`created_by`),
  CONSTRAINT `fk_ps_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ps_org` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ps_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_setup`
--

LOCK TABLES `password_setup` WRITE;
/*!40000 ALTER TABLE `password_setup` DISABLE KEYS */;
INSERT INTO `password_setup` VALUES
(1,1,55,'5d08259158f5f768343ce2f0090c4f4de662ab176150d681343568ee485937dd',1,'2025-08-24 11:11:57','2025-08-31 11:11:57','2025-08-24 11:26:52'),
(2,1,55,'b7f081947dfb7e7d5c8c87fd29463e0381fd81dd3867f3ce37d115094ccbc904',1,'2025-08-24 12:15:59','2025-08-31 12:15:59',NULL),
(3,1,55,'612e5cd27406f1728d6d1c9da4f3ef03f06cb5cb17ca4e53c74c4e501b733524',1,'2025-08-24 12:25:51','2025-08-31 12:25:51',NULL),
(4,1,55,'8d5561071e5e61e97790f6d5fdcd812443b8e4d387db3f342fe8a74121c97351',1,'2025-08-24 12:42:22','2025-08-31 12:42:22','2025-08-24 21:46:32'),
(5,1,55,'ef44adb31eaac796a08690f6da849efd05ce293e7604e78562c6233669b1d358',1,'2025-08-24 21:56:12','2025-08-31 21:56:12',NULL),
(6,1,56,'2fe02d75441e18108feba5bb9ce93d8469dbb819f1a719109eab69c70133c1c2',1,'2025-08-29 10:45:41','2025-09-05 10:45:41',NULL),
(7,1,57,'9810a7cc86a19f534d6bb12577c5a84f73cb82c834e33d559838787d120f6a9f',1,'2025-08-29 10:46:05','2025-09-05 10:46:05',NULL),
(8,1,58,'51ec087f070e3da3f1f6c68e3805c5aa95be8513c59d16ce9c5408e448f9adfd',1,'2025-08-29 10:46:27','2025-09-05 10:46:27',NULL),
(9,5,59,'d0bec758dd29cf04be8d24f57af6e50b25f972d62055912a65445883f28defd7',1,'2025-08-29 15:05:12','2025-09-05 15:05:12',NULL),
(10,5,60,'237823524379441ef7bd0027c26aacd9c2386f0c68135f6ff6caea400f51b4df',1,'2025-08-29 15:05:43','2025-09-05 15:05:43',NULL),
(11,5,61,'33906a158a2958217b87ab9ed70485ea70f511de5f9c7bf388aeebcfd9505ea0',1,'2025-09-11 10:19:36','2025-09-18 10:19:36',NULL),
(12,5,62,'ce0bd2b2f2697c06664974d3f8c5f83d8ce8abbb9c798c2fe387fdd045f07bca',1,'2025-09-12 09:27:07','2025-09-19 09:27:07',NULL),
(13,5,61,'1d97a68e5a8696d9be0c4d2da1fb396b726f4fece1b921acca66747c4ce53ddd',1,'2025-09-12 09:37:56','2025-09-19 09:37:56',NULL),
(14,5,63,'6cd8b1555bdd6af2798b7ca374e6a768da2a8909dc2bf3e04bcae51f25bb3904',1,'2025-09-17 12:24:31','2025-09-24 12:24:31',NULL),
(20,14,69,'3032812ec41b6c8dab293f693c5279976eef59ca7b7b43e3765043a7ffda59f1',NULL,'2025-09-20 16:46:42','2025-09-27 16:46:42',NULL),
(21,1,70,'66c8a0f0f083bba24a665e71e29c20462419370d6b0b589c063d607f31281cb3',1,'2025-09-27 21:46:20','2025-10-04 21:46:20',NULL),
(22,21,77,'b8362e4560a766ae7f584f7605cda59d1920e1fefb34fba363816468b3b37eca',77,'2025-10-04 19:58:40','2025-10-11 19:58:40',NULL),
(23,22,78,'c50adcbd2ea083ccb1851a1fb64cd5d73eee022f3820f02fb515fe5264a12795',78,'2025-10-04 20:16:21','2025-10-11 20:16:21',NULL),
(24,23,79,'177ef2ef23a055e9086bb8fd6abf41fbd9b48847789034620c26b91ac75f92fe',NULL,'2025-10-05 09:31:02','2025-10-12 09:31:02','2025-10-05 09:31:51'),
(25,24,80,'fd77200ce9edf2741cba1b4ae2cd761d101895d9ff3f734ef620ca660131cc7d',NULL,'2025-10-05 09:46:20','2025-10-12 09:46:20',NULL),
(26,1,92,'6825a363e9620f7b8a84c09098659cd9847438b467d931fb07fe5ecdb33615a1',1,'2025-10-14 22:37:18','2025-10-21 22:37:18',NULL);
/*!40000 ALTER TABLE `password_setup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `assessment_id` bigint(20) unsigned DEFAULT NULL,
  `amount_huf` int(11) unsigned NOT NULL,
  `status` enum('initial','pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `barion_payment_id` varchar(64) DEFAULT NULL,
  `barion_transaction_id` varchar(64) DEFAULT NULL,
  `billingo_partner_id` bigint(20) unsigned DEFAULT NULL,
  `billingo_document_id` bigint(20) unsigned DEFAULT NULL,
  `billingo_invoice_number` varchar(64) DEFAULT NULL,
  `billingo_issue_date` date DEFAULT NULL,
  `invoice_pdf_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `payments_org_idx` (`organization_id`),
  KEY `payments_assessment_idx` (`assessment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES
(5,5,13,6800,'paid','2025-09-17 08:27:42','e1f5bf21a093f0118c1e001dd8b71cc4',NULL,NULL,108795889,NULL,NULL,NULL,'2025-09-16 19:53:20','2025-09-17 08:27:43'),
(6,5,13,10800,'paid','2025-09-17 09:01:17','de5a27d4a493f0118c1e001dd8b71cc4',NULL,NULL,108799395,NULL,NULL,NULL,'2025-09-16 19:53:20','2025-09-17 09:16:22'),
(7,5,13,2300,'paid','2025-09-17 09:15:06','5a794dc2a693f0118c1e001dd8b71cc4',NULL,NULL,108800962,NULL,NULL,NULL,'2025-09-16 19:53:20','2025-09-17 09:15:07'),
(11,5,17,4750,'failed',NULL,'6c255294d9a9f0118c20001dd8b71cc5',NULL,NULL,NULL,NULL,NULL,NULL,'2025-09-17 12:18:08','2025-10-15 15:14:12'),
(17,21,NULL,14250,'paid','2025-10-04 18:00:51','ff08c3fe4ba1f0118c1e001dd8b71cc4',NULL,NULL,NULL,NULL,NULL,NULL,'2025-10-04 17:58:40','2025-10-04 18:00:51'),
(18,22,NULL,1900,'failed',NULL,'0370879367a8f0118c1e001dd8b71cc4',NULL,NULL,NULL,NULL,NULL,NULL,'2025-10-04 18:16:21','2025-10-13 19:05:32'),
(19,23,NULL,9500,'paid','2025-10-05 08:23:56','979ed88bc4a1f0118c1e001dd8b71cc4',NULL,1903867825,110060585,'QM-2025-8','2025-10-05','https://api.billingo.hu/document-access/NR80KO13PAEa5zd6Kx1oJYndDevqB69b','2025-10-05 07:43:04','2025-10-05 08:23:57'),
(20,24,NULL,14250,'initial',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-10-05 07:46:20','2025-10-05 07:46:20');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `locale` varchar(5) NOT NULL DEFAULT 'hu',
  `email_verified_at` datetime DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'normal',
  `has_auto_level_up` tinyint(4) NOT NULL DEFAULT 0,
  `removed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES
(1,'nwbusinesshu@gmail.com','Bálint Zeller','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','hu',NULL,NULL,'superadmin',0,NULL,NULL,NULL),
(33,'pahor.richard@gmail.com','Richard Pahor',NULL,'hu',NULL,NULL,'superadmin',0,NULL,NULL,NULL),
(37,'scorekingshu2@gmail.com','Kiss Ambrus',NULL,'hu',NULL,NULL,'admin',0,'2025-08-27 09:59:23',NULL,NULL),
(40,'tesztelek@nwbusiness.hu','Teszt Elek',NULL,'hu',NULL,NULL,'admin',0,NULL,'2025-08-21 09:50:29',NULL),
(41,'tesztelek@nwbusiness.hu','Teszt Elek',NULL,'hu',NULL,NULL,'admin',0,NULL,'2025-08-21 09:50:30',NULL),
(42,'kissbobe@nwbusiness.hu','Kiss Erzsébet',NULL,'hu',NULL,NULL,'admin',0,NULL,NULL,NULL),
(43,'kistamaska2@nwbusiness.hu','Kiss Tamáska',NULL,'hu',NULL,NULL,'normal',0,NULL,NULL,NULL),
(44,'gomhaj@nwbusiness.hu','Gömörő Hajnalka','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','hu',NULL,NULL,'admin',0,NULL,'2025-08-22 09:34:21',NULL),
(55,'penzugy@pahor.hu','Pahor Zsolt',NULL,'hu','2025-08-24 11:26:52',NULL,'normal',0,NULL,NULL,NULL),
(56,'gaben@nwbusiness.hu','Gábor Áron','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','hu',NULL,NULL,'normal',0,NULL,NULL,NULL),
(57,'zellerbalint.97@gmail.com','Pilot CEO 1',NULL,'hu',NULL,NULL,'ceo',0,NULL,NULL,NULL),
(58,'kissa@nwbusiness.hu','Kiss Andrea',NULL,'hu',NULL,NULL,'normal',0,NULL,NULL,NULL),
(59,'gallo@nwbusiness.hu','Teszt Veronka','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','en',NULL,NULL,'normal',0,NULL,NULL,NULL),
(60,'tesztga@nwbusiness.hu','Teszt Gáborka',NULL,'hu',NULL,NULL,'manager',0,NULL,NULL,NULL),
(61,'tesztmanager@nwbusiness.hu','Teszt Managerke','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','hu',NULL,NULL,'manager',0,NULL,NULL,NULL),
(62,'vajozsef@nwbusiness.hu','Varga József','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','hu',NULL,NULL,'ceo',0,NULL,NULL,NULL),
(63,'kisste@nwbusiness.hu','Kiss Teszterke',NULL,'hu',NULL,NULL,'normal',0,NULL,NULL,NULL),
(69,'zalint97@gmail.com','Teszt Elek',NULL,'hu',NULL,NULL,'admin',0,NULL,'2025-09-20 14:46:42',NULL),
(70,'tesztmanager2@nwbusiness.hu','Teszt Manager 2',NULL,'hu',NULL,NULL,'manager',0,NULL,NULL,NULL),
(77,'scorekingshu3@gmail.com','Payment Test','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','hu',NULL,NULL,'admin',0,NULL,'2025-10-04 17:58:40',NULL),
(78,'scorekingshu@gmail.com','Kiss Ambrus',NULL,'hu',NULL,NULL,'admin',0,NULL,'2025-10-04 18:16:21',NULL),
(79,'tesztelek2@nwbusiness.hu','Teszt Elek','$2y$10$PFbFTjn0Cn2ggdgAziU5lub/xXbrwofGv9SNFosDo75MkN9fJ9.PC','hu','2025-10-05 09:31:51',NULL,'admin',0,NULL,'2025-10-05 07:31:02',NULL),
(80,'tesztelek3@nwbusiness.hu','Kiss Ambrus',NULL,'hu',NULL,NULL,'admin',0,NULL,'2025-10-05 07:46:20',NULL),
(82,'gipszj@nwbusiness.hu','Gipsz Jakab',NULL,'hu',NULL,NULL,'normal',0,NULL,NULL,NULL),
(83,'andreab@nwbusiness.hu','Andrea Bocelli',NULL,'hu',NULL,NULL,'normal',0,NULL,NULL,NULL),
(84,'margareta@nwbusiness.hu','Margaret Island',NULL,'hu',NULL,NULL,'normal',0,NULL,NULL,NULL),
(92,'tesztsanyi@nwbusiness.hu','Teszt Sanyi',NULL,'hu',NULL,NULL,'normal',0,'2025-10-14 22:47:58',NULL,NULL);
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_bonus_malus`
--

DROP TABLE IF EXISTS `user_bonus_malus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_bonus_malus` (
  `user_id` bigint(20) unsigned NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `level` smallint(6) NOT NULL DEFAULT 4,
  `month` date NOT NULL,
  PRIMARY KEY (`user_id`,`month`,`organization_id`),
  KEY `user_bonus_malus_fk1_idx` (`user_id`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `user_bonus_malus_fk1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_bonus_malus_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_bonus_malus`
--

LOCK TABLES `user_bonus_malus` WRITE;
/*!40000 ALTER TABLE `user_bonus_malus` DISABLE KEYS */;
INSERT INTO `user_bonus_malus` VALUES
(43,1,10,'2025-08-21'),
(43,1,5,'2025-10-01'),
(55,1,9,'2025-08-01'),
(55,1,5,'2025-09-01'),
(55,1,15,'2025-10-01'),
(56,1,6,'2025-08-01'),
(57,1,1,'2025-08-01'),
(57,1,6,'2025-10-01'),
(58,1,5,'2025-08-01'),
(59,5,4,'2025-08-01'),
(59,5,5,'2025-09-01'),
(60,5,4,'2025-08-01'),
(60,5,5,'2025-09-01'),
(61,5,5,'2025-09-01'),
(62,5,13,'2025-09-01'),
(63,5,5,'2025-09-01'),
(70,1,5,'2025-09-01'),
(82,1,5,'2025-10-01'),
(83,1,5,'2025-10-01'),
(84,1,5,'2025-10-01'),
(92,1,5,'2025-10-01');
/*!40000 ALTER TABLE `user_bonus_malus` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_user_bonus_malus_bi` BEFORE INSERT ON `user_bonus_malus` FOR EACH ROW BEGIN
  IF NOT is_org_member(NEW.organization_id, NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='user_id not org member';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_user_bonus_malus_bu` BEFORE UPDATE ON `user_bonus_malus` FOR EACH ROW BEGIN
  IF NOT is_org_member(NEW.organization_id, NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='user_id not org member';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `user_ceo_rank`
--

DROP TABLE IF EXISTS `user_ceo_rank`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_ceo_rank` (
  `assessment_id` bigint(20) unsigned NOT NULL,
  `ceo_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `value` smallint(6) NOT NULL,
  UNIQUE KEY `uq_user_ceo_rank` (`assessment_id`,`ceo_id`,`user_id`),
  KEY `ceo_rank_fk1_idx` (`assessment_id`),
  KEY `ceo_rank_fk2_idx` (`ceo_id`),
  KEY `ceo_rank_fk3_idx` (`user_id`),
  KEY `idx_user_ceo_rank_assessment_a1` (`assessment_id`),
  CONSTRAINT `user_ceo_rank_fk1` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_ceo_rank_fk2` FOREIGN KEY (`ceo_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_ceo_rank_fk3` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_ceo_rank`
--

LOCK TABLES `user_ceo_rank` WRITE;
/*!40000 ALTER TABLE `user_ceo_rank` DISABLE KEYS */;
INSERT INTO `user_ceo_rank` VALUES
(8,57,43,86),
(8,57,55,78),
(8,57,56,100),
(8,57,58,50),
(9,57,43,86),
(9,57,55,78),
(9,57,56,100),
(9,57,58,50),
(13,57,43,86),
(13,57,55,100),
(13,57,56,50),
(13,57,58,78),
(17,61,59,100),
(17,62,60,50),
(17,62,61,86),
(17,62,63,100);
/*!40000 ALTER TABLE `user_ceo_rank` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_user_ceo_rank_bi` BEFORE INSERT ON `user_ceo_rank` FOR EACH ROW BEGIN
  DECLARE v_org BIGINT UNSIGNED;
  SELECT organization_id INTO v_org FROM assessment WHERE id = NEW.assessment_id LIMIT 1;

  IF v_org IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assessment has no organization';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM organization_user ou WHERE ou.organization_id = v_org AND ou.user_id = NEW.ceo_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ceo_id is not a member of the assessment organization';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM organization_user ou WHERE ou.organization_id = v_org AND ou.user_id = NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is not a member of the assessment organization';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_user_ceo_rank_bu` BEFORE UPDATE ON `user_ceo_rank` FOR EACH ROW BEGIN
  DECLARE v_org BIGINT UNSIGNED;
  SELECT organization_id INTO v_org FROM assessment WHERE id = NEW.assessment_id LIMIT 1;

  IF v_org IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assessment has no organization';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM organization_user ou WHERE ou.organization_id = v_org AND ou.user_id = NEW.ceo_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ceo_id is not a member of the assessment organization';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM organization_user ou WHERE ou.organization_id = v_org AND ou.user_id = NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is not a member of the assessment organization';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `user_competency`
--

DROP TABLE IF EXISTS `user_competency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_competency` (
  `user_id` bigint(20) unsigned NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `competency_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`competency_id`,`organization_id`),
  UNIQUE KEY `uc_user_comp_org_unique` (`user_id`,`organization_id`,`competency_id`),
  KEY `user_competency_fk1_idx` (`user_id`),
  KEY `user_competency_fk1_idx1` (`competency_id`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `user_competency_fk1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_competency_fk2` FOREIGN KEY (`competency_id`) REFERENCES `competency` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_competency_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_competency`
--

LOCK TABLES `user_competency` WRITE;
/*!40000 ALTER TABLE `user_competency` DISABLE KEYS */;
INSERT INTO `user_competency` VALUES
(43,1,6),
(43,1,9),
(55,1,1),
(55,1,2),
(55,1,3),
(56,1,4),
(56,1,7),
(56,1,10),
(57,1,2),
(57,1,3),
(57,1,5),
(57,1,6),
(57,1,7),
(58,1,3),
(58,1,6),
(58,1,8),
(59,5,1),
(59,5,4),
(59,5,6),
(59,5,7),
(60,5,4),
(60,5,6),
(60,5,7),
(60,5,9),
(61,5,5),
(61,5,8),
(61,5,10),
(62,5,1),
(62,5,2),
(62,5,5),
(62,5,6),
(62,5,8),
(63,5,1),
(63,5,4),
(63,5,6),
(63,5,7);
/*!40000 ALTER TABLE `user_competency` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_user_competency_bi` BEFORE INSERT ON `user_competency` FOR EACH ROW BEGIN
  DECLARE v_comp_org BIGINT UNSIGNED;
  SELECT organization_id INTO v_comp_org FROM competency WHERE id = NEW.competency_id;

  IF NOT is_org_member(NEW.organization_id, NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='User is not member of organization';
  END IF;

  IF v_comp_org IS NOT NULL AND v_comp_org <> NEW.organization_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Competency belongs to a different organization';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_user_competency_bu` BEFORE UPDATE ON `user_competency` FOR EACH ROW BEGIN
  DECLARE v_comp_org BIGINT UNSIGNED;
  SELECT organization_id INTO v_comp_org FROM competency WHERE id = NEW.competency_id;

  IF NOT is_org_member(NEW.organization_id, NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='User is not member of organization';
  END IF;

  IF v_comp_org IS NOT NULL AND v_comp_org <> NEW.organization_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Competency belongs to a different organization';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `user_competency_sources`
--

DROP TABLE IF EXISTS `user_competency_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_competency_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `competency_id` bigint(20) unsigned NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `source_type` enum('manual','group') NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL COMMENT 'competency_group_id if source_type=group, NULL if manual',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_source` (`user_id`,`competency_id`,`organization_id`,`source_type`,`source_id`),
  KEY `idx_user_comp` (`user_id`,`competency_id`),
  KEY `idx_source` (`source_type`,`source_id`),
  KEY `idx_org` (`organization_id`),
  KEY `fk_competency_sources_competency` (`competency_id`),
  KEY `fk_competency_sources_source_id` (`source_id`),
  CONSTRAINT `fk_competency_sources_competency` FOREIGN KEY (`competency_id`) REFERENCES `competency` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_competency_sources_org` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_competency_sources_source_id` FOREIGN KEY (`source_id`) REFERENCES `competency_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_competency_sources_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_competency_sources`
--

LOCK TABLES `user_competency_sources` WRITE;
/*!40000 ALTER TABLE `user_competency_sources` DISABLE KEYS */;
INSERT INTO `user_competency_sources` VALUES
(1,43,6,1,'manual',NULL,'2025-09-29 17:04:51'),
(2,43,9,1,'manual',NULL,'2025-09-29 17:04:51'),
(3,55,1,1,'manual',NULL,'2025-09-29 17:04:51'),
(4,55,2,1,'manual',NULL,'2025-09-29 17:04:51'),
(5,55,3,1,'manual',NULL,'2025-09-29 17:04:51'),
(6,56,4,1,'manual',NULL,'2025-09-29 17:04:51'),
(7,56,7,1,'manual',NULL,'2025-09-29 17:04:51'),
(8,56,10,1,'manual',NULL,'2025-09-29 17:04:51'),
(9,57,5,1,'manual',NULL,'2025-09-29 17:04:51'),
(10,57,7,1,'manual',NULL,'2025-09-29 17:04:51'),
(11,58,3,1,'manual',NULL,'2025-09-29 17:04:51'),
(12,58,6,1,'manual',NULL,'2025-09-29 17:04:51'),
(13,58,8,1,'manual',NULL,'2025-09-29 17:04:51'),
(14,59,1,5,'manual',NULL,'2025-09-29 17:04:51'),
(15,59,4,5,'manual',NULL,'2025-09-29 17:04:51'),
(16,59,6,5,'manual',NULL,'2025-09-29 17:04:51'),
(17,59,7,5,'manual',NULL,'2025-09-29 17:04:51'),
(18,60,4,5,'manual',NULL,'2025-09-29 17:04:51'),
(19,60,6,5,'manual',NULL,'2025-09-29 17:04:51'),
(20,60,7,5,'manual',NULL,'2025-09-29 17:04:51'),
(21,60,9,5,'manual',NULL,'2025-09-29 17:04:51'),
(22,61,5,5,'manual',NULL,'2025-09-29 17:04:51'),
(23,61,8,5,'manual',NULL,'2025-09-29 17:04:51'),
(24,61,10,5,'manual',NULL,'2025-09-29 17:04:51'),
(25,62,1,5,'manual',NULL,'2025-09-29 17:04:51'),
(26,62,2,5,'manual',NULL,'2025-09-29 17:04:51'),
(27,62,5,5,'manual',NULL,'2025-09-29 17:04:51'),
(28,62,6,5,'manual',NULL,'2025-09-29 17:04:51'),
(29,62,8,5,'manual',NULL,'2025-09-29 17:04:51'),
(30,63,1,5,'manual',NULL,'2025-09-29 17:04:51'),
(31,63,4,5,'manual',NULL,'2025-09-29 17:04:51'),
(32,63,6,5,'manual',NULL,'2025-09-29 17:04:51'),
(33,63,7,5,'manual',NULL,'2025-09-29 17:04:51'),
(68,57,2,1,'group',4,'2025-09-29 17:19:12'),
(69,57,3,1,'group',4,'2025-09-29 17:19:12'),
(70,57,5,1,'group',4,'2025-09-29 17:19:12'),
(71,57,6,1,'manual',NULL,'2025-10-14 20:47:12');
/*!40000 ALTER TABLE `user_competency_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_competency_submit`
--

DROP TABLE IF EXISTS `user_competency_submit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_competency_submit` (
  `assessment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `telemetry_ai` longtext DEFAULT NULL COMMENT 'AI scoring/result JSON',
  `telemetry_raw` longtext DEFAULT NULL COMMENT 'Client+server telemetry JSON',
  PRIMARY KEY (`assessment_id`,`user_id`,`target_id`),
  UNIQUE KEY `uq_user_comp_submit` (`assessment_id`,`user_id`,`target_id`),
  UNIQUE KEY `uq_user_comp_submit_triple` (`assessment_id`,`user_id`,`target_id`),
  KEY `user_competency_submit_fk1_idx` (`assessment_id`),
  KEY `user_competency_submit_fk2_idx` (`user_id`),
  KEY `user_competency_submit_fk3_idx` (`target_id`),
  KEY `idx_user_comp_submit_assessment_a1` (`assessment_id`),
  CONSTRAINT `user_competency_submit_fk1` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_competency_submit_fk2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_competency_submit_fk3` FOREIGN KEY (`target_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_competency_submit`
--

LOCK TABLES `user_competency_submit` WRITE;
/*!40000 ALTER TABLE `user_competency_submit` DISABLE KEYS */;
INSERT INTO `user_competency_submit` VALUES
(8,43,43,'2025-08-29 14:08:25',NULL,NULL),
(8,57,43,'2025-08-29 16:24:38',NULL,NULL),
(8,57,55,'2025-08-29 16:25:35',NULL,NULL),
(8,57,56,'2025-08-29 16:26:05',NULL,NULL),
(8,57,57,'2025-08-29 16:24:57',NULL,NULL),
(8,57,58,'2025-08-29 16:25:49',NULL,NULL),
(9,43,43,'2025-08-31 21:21:26',NULL,'\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"84345086-b360-467e-b214-c4c33eb98e73\\\",\\\"started_at\\\":\\\"2025-08-31T19:21:08.486Z\\\",\\\"finished_at\\\":\\\"2025-08-31T19:21:26.289Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":2560,\\\"viewport_h\\\":945},\\\"items_count\\\":8,\\\"display_order\\\":[48,15,49,38,35,37,36,47],\\\"total_ms\\\":17804,\\\"visible_ms\\\":17804,\\\"active_ms\\\":17804,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":8,\\\"keydowns\\\":0,\\\"scrolls\\\":208,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":8},\\\"items\\\":[{\\\"question_id\\\":48,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":76,\\\"view_read_ms\\\":9204,\\\"view_read_ms_per_100ch\\\":12111,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":9224,\\\"value_path\\\":[{\\\"ms\\\":9224,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":2030,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":15,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":104,\\\"view_read_ms\\\":9745,\\\"view_read_ms_per_100ch\\\":9370,\\\"seq_read_ms_raw\\\":541,\\\"seq_read_ms_active\\\":541,\\\"seq_read_ms_per_100ch\\\":520,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":9765,\\\"value_path\\\":[{\\\"ms\\\":9765,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":2974,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":49,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":44,\\\"view_read_ms\\\":10127,\\\"view_read_ms_per_100ch\\\":23016,\\\"seq_read_ms_raw\\\":382,\\\"seq_read_ms_active\\\":382,\\\"seq_read_ms_per_100ch\\\":868,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":10147,\\\"value_path\\\":[{\\\"ms\\\":10147,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1202,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":38,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":69,\\\"view_read_ms\\\":11205,\\\"view_read_ms_per_100ch\\\":16239,\\\"seq_read_ms_raw\\\":1078,\\\"seq_read_ms_active\\\":1078,\\\"seq_read_ms_per_100ch\\\":1562,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":11225,\\\"value_path\\\":[{\\\"ms\\\":11225,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1066,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":35,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":48,\\\"view_read_ms\\\":8578,\\\"view_read_ms_per_100ch\\\":17871,\\\"seq_read_ms_raw\\\":684,\\\"seq_read_ms_active\\\":684,\\\"seq_read_ms_per_100ch\\\":1425,\\\"first_seen_ms\\\":3331,\\\"first_interaction_ms\\\":11909,\\\"value_path\\\":[{\\\"ms\\\":11909,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1648,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":37,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":76,\\\"view_read_ms\\\":9534,\\\"view_read_ms_per_100ch\\\":12545,\\\"seq_read_ms_raw\\\":1032,\\\"seq_read_ms_active\\\":1032,\\\"seq_read_ms_per_100ch\\\":1358,\\\"first_seen_ms\\\":3407,\\\"first_interaction_ms\\\":12941,\\\"value_path\\\":[{\\\"ms\\\":12941,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":827,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":36,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":69,\\\"view_read_ms\\\":10893,\\\"view_read_ms_per_100ch\\\":15787,\\\"seq_read_ms_raw\\\":496,\\\"seq_read_ms_active\\\":496,\\\"seq_read_ms_per_100ch\\\":719,\\\"first_seen_ms\\\":3492,\\\"first_interaction_ms\\\":14385,\\\"value_path\\\":[{\\\"ms\\\":14385,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1258,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":47,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":84,\\\"view_read_ms\\\":10304,\\\"view_read_ms_per_100ch\\\":12267,\\\"seq_read_ms_raw\\\":948,\\\"seq_read_ms_active\\\":948,\\\"seq_read_ms_per_100ch\\\":1129,\\\"first_seen_ms\\\":3585,\\\"first_interaction_ms\\\":13889,\\\"value_path\\\":[{\\\"ms\\\":13889,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":2427,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":9,\\\"user_id\\\":43,\\\"target_id\\\":43,\\\"relation_type\\\":\\\"self\\\",\\\"answers_count\\\":8,\\\"items_count_server\\\":8,\\\"items_count_client\\\":8,\\\"server_received_at\\\":\\\"2025-08-31T21:21:26+02:00\\\",\\\"client_started_at\\\":\\\"2025-08-31T19:21:08.486Z\\\",\\\"client_finished_at\\\":\\\"2025-08-31T19:21:26.289Z\\\",\\\"measurement_uuid\\\":\\\"84345086-b360-467e-b214-c4c33eb98e73\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":8,\\\"value_counts\\\":{\\\"6\\\":4,\\\"7\\\":2,\\\"5\\\":2},\\\"dominant_value\\\":6,\\\"dominant_share\\\":0.5,\\\"extremes_share\\\":0.25,\\\"all_same_value\\\":false,\\\"mean_percent\\\":85.7099999999999937472239253111183643341064453125,\\\"stddev_percent\\\":10.0999999999999996447286321199499070644378662109375},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":1,\\\"fast_clicks_p1000\\\":0.5,\\\"pace_cv\\\":0.419003587353994177977511981225688941776752471923828125,\\\"pace_median_ms\\\":858,\\\"pace_iqr_ms\\\":[382,1078],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":0,\\\"assessment_span\\\":\\\"previous_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":85,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":1,\\\"window_days\\\":0,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"relation_mix\\\":{\\\"colleague\\\":1},\\\"device_mix\\\":{\\\"desktop\\\":1},\\\"trust_summary\\\":{\\\"median\\\":18,\\\"iqr\\\":[18,18],\\\"low_rate\\\":0,\\\"high_rate\\\":1,\\\"trend\\\":\\\"flat\\\"},\\\"flags_top\\\":[],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":1576,\\\"iqr\\\":[1576,1576],\\\"coverage\\\":1},\\\"uniform_ratio\\\":{\\\"median\\\":0.40000000000000002220446049250313080847263336181640625,\\\"iqr\\\":[0.40000000000000002220446049250313080847263336181640625,0.40000000000000002220446049250313080847263336181640625],\\\"coverage\\\":1},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.462000000000000021760371282653068192303180694580078125,\\\"iqr\\\":[0.462000000000000021760371282653068192303180694580078125,0.462000000000000021760371282653068192303180694580078125],\\\"coverage\\\":1},\\\"fast_pass_rate\\\":{\\\"median\\\":0,\\\"iqr\\\":[0,0],\\\"coverage\\\":1}},\\\"by_relation\\\":{\\\"colleague\\\":{\\\"n\\\":1,\\\"trust_median\\\":18,\\\"flags_top\\\":[]}},\\\"for_current_target\\\":null}}\"'),
(9,43,55,'2025-08-31 10:34:14','{\"trust_score\":18,\"flags\":[],\"rationale\":\"The submission shows no critical issues: all 15 items answered, no count mismatches or all-same-value patterns. Timing is reasonable with an average of 1.576 seconds per item, no \'too fast\' flags, and zero fast_pass_rate, indicating thoughtful responding. Uniformity is moderate (0.4), showing some variability in responses. The zigzag index is moderate (0.462), suggesting non-uniform patterns. Focus times per item are substantive, ranging from about 845ms to over 2 seconds, indicating engagement. No flags of suspicious behavior are detected. Given the \'be_kind\' guidance and cold start history with no prior reliability data, a high trust score of 18 is warranted, leaving a small margin for unknown factors.\",\"relation_type\":\"colleague\",\"target_id\":55,\"ai_timestamp\":\"2025-08-31T10:35:00+02:00\",\"features_snapshot\":{\"avg_ms_per_item\":1576,\"uniform_ratio\":0.40000000000000002220446049250313080847263336181640625,\"entropy\":null,\"zigzag_index\":0.462000000000000021760371282653068192303180694580078125,\"fast_pass_rate\":0,\"device_type\":\"desktop\"}}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"83ab8197-81e9-4b48-9e59-e4cd496ab15d\\\",\\\"started_at\\\":\\\"2025-08-31T08:33:50.580Z\\\",\\\"finished_at\\\":\\\"2025-08-31T08:34:14.213Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1.5,\\\"viewport_w\\\":477,\\\"viewport_h\\\":833},\\\"items_count\\\":15,\\\"display_order\\\":[29,26,28,1,5,13,3,6,7,4,10,9,8,11,46],\\\"total_ms\\\":23633,\\\"visible_ms\\\":23633,\\\"active_ms\\\":21019,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":15,\\\"keydowns\\\":0,\\\"scrolls\\\":223,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":15},\\\"items\\\":[{\\\"question_id\\\":29,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":35,\\\"first_interaction_ms\\\":2831,\\\"value_path\\\":[{\\\"ms\\\":2831,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":2259,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":26,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":35,\\\"first_interaction_ms\\\":4281,\\\"value_path\\\":[{\\\"ms\\\":4281,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1924,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":28,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":35,\\\"first_interaction_ms\\\":5627,\\\"value_path\\\":[{\\\"ms\\\":5627,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":845,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":1,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":4714,\\\"first_interaction_ms\\\":6449,\\\"value_path\\\":[{\\\"ms\\\":6449,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1187,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":5,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":6713,\\\"first_interaction_ms\\\":8437,\\\"value_path\\\":[{\\\"ms\\\":8437,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1660,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":13,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":7497,\\\"first_interaction_ms\\\":9767,\\\"value_path\\\":[{\\\"ms\\\":9767,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1760,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":3,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":8614,\\\"first_interaction_ms\\\":10936,\\\"value_path\\\":[{\\\"ms\\\":10936,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1023,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":6,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":10014,\\\"first_interaction_ms\\\":12061,\\\"value_path\\\":[{\\\"ms\\\":12061,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":882,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":7,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":11097,\\\"first_interaction_ms\\\":13080,\\\"value_path\\\":[{\\\"ms\\\":13080,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1286,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":4,\\\"index\\\":10,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":12198,\\\"first_interaction_ms\\\":14155,\\\"value_path\\\":[{\\\"ms\\\":14155,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":1118,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":10,\\\"index\\\":11,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":13530,\\\"first_interaction_ms\\\":15705,\\\"value_path\\\":[{\\\"ms\\\":15705,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":1616,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":9,\\\"index\\\":12,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":14364,\\\"first_interaction_ms\\\":17377,\\\"value_path\\\":[{\\\"ms\\\":17377,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":947,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":8,\\\"index\\\":13,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":16014,\\\"first_interaction_ms\\\":18240,\\\"value_path\\\":[{\\\"ms\\\":18240,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":1382,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":11,\\\"index\\\":14,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":16097,\\\"first_interaction_ms\\\":19392,\\\"value_path\\\":[{\\\"ms\\\":19392,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1067,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":46,\\\"index\\\":15,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":18481,\\\"first_interaction_ms\\\":20736,\\\"value_path\\\":[{\\\"ms\\\":20736,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1023,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":9,\\\"user_id\\\":43,\\\"target_id\\\":55,\\\"relation_type\\\":\\\"colleague\\\",\\\"answers_count\\\":15,\\\"items_count_server\\\":15,\\\"items_count_client\\\":15,\\\"server_received_at\\\":\\\"2025-08-31T10:34:14+02:00\\\",\\\"client_started_at\\\":\\\"2025-08-31T08:33:50.580Z\\\",\\\"client_finished_at\\\":\\\"2025-08-31T08:34:14.213Z\\\",\\\"measurement_uuid\\\":\\\"83ab8197-81e9-4b48-9e59-e4cd496ab15d\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false},\\\"history_digest\\\":{\\\"n\\\":0,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"message\\\":\\\"Nincs kor\\u00e1bbi AI-\\u00e9rt\\u00e9kel\\u00e9s. \\u00daj felhaszn\\u00e1l\\u00f3 \\u2013 legy\\u00fcnk k\\u00edm\\u00e9letesek.\\\"}}\"'),
(9,43,56,'2025-08-31 09:29:41',NULL,'\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"7ba6e7d8-03f0-42a1-afdf-d2c80727cf79\\\",\\\"started_at\\\":\\\"2025-08-31T07:29:18.532Z\\\",\\\"finished_at\\\":\\\"2025-08-31T07:29:45.166Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1.5,\\\"viewport_w\\\":477,\\\"viewport_h\\\":833},\\\"items_count\\\":12,\\\"display_order\\\":[34,50,32,41,24,22,23,25,44,45,40,39],\\\"total_ms\\\":26634,\\\"visible_ms\\\":26634,\\\"active_ms\\\":26634,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":12,\\\"keydowns\\\":0,\\\"scrolls\\\":309,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":12},\\\"items\\\":[{\\\"question_id\\\":34,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":38,\\\"first_interaction_ms\\\":5491,\\\"value_path\\\":[{\\\"ms\\\":5491,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":2802,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":50,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":38,\\\"first_interaction_ms\\\":6531,\\\"value_path\\\":[{\\\"ms\\\":6531,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":2750,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":32,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":1943,\\\"first_interaction_ms\\\":7567,\\\"value_path\\\":[{\\\"ms\\\":7567,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":2396,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":41,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":3376,\\\"first_interaction_ms\\\":9193,\\\"value_path\\\":[{\\\"ms\\\":9193,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1817,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":24,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":8042,\\\"first_interaction_ms\\\":11334,\\\"value_path\\\":[{\\\"ms\\\":11334,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1384,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":22,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":9610,\\\"first_interaction_ms\\\":12334,\\\"value_path\\\":[{\\\"ms\\\":12334,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1625,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":23,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":12526,\\\"first_interaction_ms\\\":14599,\\\"value_path\\\":[{\\\"ms\\\":14599,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":919,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":25,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":12675,\\\"first_interaction_ms\\\":15866,\\\"value_path\\\":[{\\\"ms\\\":15866,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":2941,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":44,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":16259,\\\"first_interaction_ms\\\":19537,\\\"value_path\\\":[{\\\"ms\\\":19537,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":1195,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":45,\\\"index\\\":10,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":16993,\\\"first_interaction_ms\\\":20645,\\\"value_path\\\":[{\\\"ms\\\":20645,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":2996,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":40,\\\"index\\\":11,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":17659,\\\"first_interaction_ms\\\":22521,\\\"value_path\\\":[{\\\"ms\\\":22521,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":839,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":39,\\\"index\\\":12,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"first_seen_ms\\\":20858,\\\"first_interaction_ms\\\":23563,\\\"value_path\\\":[{\\\"ms\\\":23563,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1825,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":9,\\\"user_id\\\":43,\\\"target_id\\\":56,\\\"relation_type\\\":\\\"colleague\\\",\\\"answers_count\\\":12,\\\"items_count_server\\\":12,\\\"items_count_client\\\":12,\\\"server_received_at\\\":\\\"2025-08-31T09:29:41+02:00\\\",\\\"client_started_at\\\":\\\"2025-08-31T07:29:18.532Z\\\",\\\"client_finished_at\\\":\\\"2025-08-31T07:29:45.166Z\\\",\\\"measurement_uuid\\\":\\\"7ba6e7d8-03f0-42a1-afdf-d2c80727cf79\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false},\\\"history_digest\\\":{\\\"n\\\":0,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"message\\\":\\\"Nincs kor\\u00e1bbi AI-\\u00e9rt\\u00e9kel\\u00e9s. \\u00daj felhaszn\\u00e1l\\u00f3 \\u2013 legy\\u00fcnk k\\u00edm\\u00e9letesek.\\\"}}\"'),
(9,57,43,'2025-08-31 21:41:48',NULL,'\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"ece06e7e-af46-40de-934f-281c58bdbfe0\\\",\\\"started_at\\\":\\\"2025-08-31T19:41:37.007Z\\\",\\\"finished_at\\\":\\\"2025-08-31T19:41:48.017Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":2560,\\\"viewport_h\\\":945},\\\"items_count\\\":8,\\\"display_order\\\":[15,49,48,37,47,38,36,35],\\\"total_ms\\\":11010,\\\"visible_ms\\\":11010,\\\"active_ms\\\":11010,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":8,\\\"keydowns\\\":0,\\\"scrolls\\\":205,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":8},\\\"items\\\":[{\\\"question_id\\\":15,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":98,\\\"view_read_ms\\\":2760,\\\"view_read_ms_per_100ch\\\":2816,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":18,\\\"first_interaction_ms\\\":2778,\\\"value_path\\\":[{\\\"ms\\\":2778,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1011,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":49,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":43,\\\"view_read_ms\\\":3320,\\\"view_read_ms_per_100ch\\\":7721,\\\"seq_read_ms_raw\\\":560,\\\"seq_read_ms_active\\\":560,\\\"seq_read_ms_per_100ch\\\":1302,\\\"first_seen_ms\\\":18,\\\"first_interaction_ms\\\":3338,\\\"value_path\\\":[{\\\"ms\\\":3338,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1179,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":48,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":85,\\\"view_read_ms\\\":3996,\\\"view_read_ms_per_100ch\\\":4701,\\\"seq_read_ms_raw\\\":676,\\\"seq_read_ms_active\\\":676,\\\"seq_read_ms_per_100ch\\\":795,\\\"first_seen_ms\\\":18,\\\"first_interaction_ms\\\":4014,\\\"value_path\\\":[{\\\"ms\\\":4014,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1801,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":37,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":71,\\\"view_read_ms\\\":5362,\\\"view_read_ms_per_100ch\\\":7552,\\\"seq_read_ms_raw\\\":1366,\\\"seq_read_ms_active\\\":1366,\\\"seq_read_ms_per_100ch\\\":1924,\\\"first_seen_ms\\\":18,\\\"first_interaction_ms\\\":5380,\\\"value_path\\\":[{\\\"ms\\\":5380,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":562,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":47,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":48,\\\"view_read_ms\\\":5146,\\\"view_read_ms_per_100ch\\\":10721,\\\"seq_read_ms_raw\\\":465,\\\"seq_read_ms_active\\\":465,\\\"seq_read_ms_per_100ch\\\":969,\\\"first_seen_ms\\\":699,\\\"first_interaction_ms\\\":5845,\\\"value_path\\\":[{\\\"ms\\\":5845,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":594,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":38,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":66,\\\"view_read_ms\\\":5284,\\\"view_read_ms_per_100ch\\\":8006,\\\"seq_read_ms_raw\\\":443,\\\"seq_read_ms_active\\\":443,\\\"seq_read_ms_per_100ch\\\":671,\\\"first_seen_ms\\\":1004,\\\"first_interaction_ms\\\":6288,\\\"value_path\\\":[{\\\"ms\\\":6288,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":996,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":36,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":73,\\\"view_read_ms\\\":6303,\\\"view_read_ms_per_100ch\\\":8634,\\\"seq_read_ms_raw\\\":1032,\\\"seq_read_ms_active\\\":1032,\\\"seq_read_ms_per_100ch\\\":1414,\\\"first_seen_ms\\\":1017,\\\"first_interaction_ms\\\":7320,\\\"value_path\\\":[{\\\"ms\\\":7320,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":531,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":35,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":66,\\\"view_read_ms\\\":6779,\\\"view_read_ms_per_100ch\\\":10271,\\\"seq_read_ms_raw\\\":505,\\\"seq_read_ms_active\\\":505,\\\"seq_read_ms_per_100ch\\\":765,\\\"first_seen_ms\\\":1046,\\\"first_interaction_ms\\\":7825,\\\"value_path\\\":[{\\\"ms\\\":7825,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":825,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":9,\\\"user_id\\\":57,\\\"target_id\\\":43,\\\"relation_type\\\":\\\"ceo\\\",\\\"answers_count\\\":8,\\\"items_count_server\\\":8,\\\"items_count_client\\\":8,\\\"server_received_at\\\":\\\"2025-08-31T21:41:48+02:00\\\",\\\"client_started_at\\\":\\\"2025-08-31T19:41:37.007Z\\\",\\\"client_finished_at\\\":\\\"2025-08-31T19:41:48.017Z\\\",\\\"measurement_uuid\\\":\\\"ece06e7e-af46-40de-934f-281c58bdbfe0\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":8,\\\"value_counts\\\":{\\\"6\\\":4,\\\"5\\\":4},\\\"dominant_value\\\":6,\\\"dominant_share\\\":0.5,\\\"extremes_share\\\":0,\\\"all_same_value\\\":false,\\\"mean_percent\\\":78.56999999999999317878973670303821563720703125,\\\"stddev_percent\\\":7.13999999999999968025576890795491635799407958984375},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":1,\\\"fast_clicks_p1000\\\":0.713999999999999968025576890795491635799407958984375,\\\"pace_cv\\\":0.44794749594081106902621058907243423163890838623046875,\\\"pace_median_ms\\\":560,\\\"pace_iqr_ms\\\":[443,1032],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":0,\\\"assessment_span\\\":\\\"previous_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":77.5,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":0,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"message\\\":\\\"Nincs kor\\u00e1bbi AI-\\u00e9rt\\u00e9kel\\u00e9s. \\u00daj felhaszn\\u00e1l\\u00f3 \\u2013 legy\\u00fcnk k\\u00edm\\u00e9letesek.\\\"}}\"'),
(9,57,55,'2025-08-31 21:51:14',NULL,'\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"b5d7a1ce-0a17-4afb-a4c7-3dbdbab57013\\\",\\\"started_at\\\":\\\"2025-08-31T19:50:58.321Z\\\",\\\"finished_at\\\":\\\"2025-08-31T19:51:13.457Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":2560,\\\"viewport_h\\\":945},\\\"items_count\\\":15,\\\"display_order\\\":[26,28,29,1,3,13,4,7,6,5,10,46,11,8,9],\\\"total_ms\\\":15136,\\\"visible_ms\\\":15136,\\\"active_ms\\\":15136,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":15,\\\"keydowns\\\":0,\\\"scrolls\\\":121,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":15},\\\"items\\\":[{\\\"question_id\\\":26,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":88,\\\"view_read_ms\\\":1332,\\\"view_read_ms_per_100ch\\\":1514,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":22,\\\"first_interaction_ms\\\":1354,\\\"value_path\\\":[{\\\"ms\\\":1354,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":640,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":28,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":97,\\\"view_read_ms\\\":2339,\\\"view_read_ms_per_100ch\\\":2411,\\\"seq_read_ms_raw\\\":1007,\\\"seq_read_ms_active\\\":1007,\\\"seq_read_ms_per_100ch\\\":1038,\\\"first_seen_ms\\\":22,\\\"first_interaction_ms\\\":2361,\\\"value_path\\\":[{\\\"ms\\\":2361,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1359,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":29,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":47,\\\"view_read_ms\\\":2953,\\\"view_read_ms_per_100ch\\\":6283,\\\"seq_read_ms_raw\\\":614,\\\"seq_read_ms_active\\\":614,\\\"seq_read_ms_per_100ch\\\":1306,\\\"first_seen_ms\\\":22,\\\"first_interaction_ms\\\":2975,\\\"value_path\\\":[{\\\"ms\\\":2975,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":947,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":1,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":3341,\\\"view_read_ms_per_100ch\\\":4025,\\\"seq_read_ms_raw\\\":388,\\\"seq_read_ms_active\\\":388,\\\"seq_read_ms_per_100ch\\\":467,\\\"first_seen_ms\\\":22,\\\"first_interaction_ms\\\":3363,\\\"value_path\\\":[{\\\"ms\\\":3363,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":512,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":3,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":88,\\\"view_read_ms\\\":1143,\\\"view_read_ms_per_100ch\\\":1299,\\\"seq_read_ms_raw\\\":1406,\\\"seq_read_ms_active\\\":1406,\\\"seq_read_ms_per_100ch\\\":1598,\\\"first_seen_ms\\\":3626,\\\"first_interaction_ms\\\":4769,\\\"value_path\\\":[{\\\"ms\\\":4769,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":435,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":13,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":67,\\\"view_read_ms\\\":1447,\\\"view_read_ms_per_100ch\\\":2160,\\\"seq_read_ms_raw\\\":356,\\\"seq_read_ms_active\\\":356,\\\"seq_read_ms_per_100ch\\\":531,\\\"first_seen_ms\\\":3678,\\\"first_interaction_ms\\\":5125,\\\"value_path\\\":[{\\\"ms\\\":5125,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":792,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":4,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":60,\\\"view_read_ms\\\":1575,\\\"view_read_ms_per_100ch\\\":2625,\\\"seq_read_ms_raw\\\":382,\\\"seq_read_ms_active\\\":382,\\\"seq_read_ms_per_100ch\\\":637,\\\"first_seen_ms\\\":3932,\\\"first_interaction_ms\\\":5507,\\\"value_path\\\":[{\\\"ms\\\":5507,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":568,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":7,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":76,\\\"view_read_ms\\\":1924,\\\"view_read_ms_per_100ch\\\":2532,\\\"seq_read_ms_raw\\\":388,\\\"seq_read_ms_active\\\":388,\\\"seq_read_ms_per_100ch\\\":511,\\\"first_seen_ms\\\":3971,\\\"first_interaction_ms\\\":5895,\\\"value_path\\\":[{\\\"ms\\\":5895,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":739,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":6,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":43,\\\"view_read_ms\\\":2943,\\\"view_read_ms_per_100ch\\\":6844,\\\"seq_read_ms_raw\\\":1059,\\\"seq_read_ms_active\\\":1059,\\\"seq_read_ms_per_100ch\\\":2463,\\\"first_seen_ms\\\":4011,\\\"first_interaction_ms\\\":6954,\\\"value_path\\\":[{\\\"ms\\\":6954,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":509,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":5,\\\"index\\\":10,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":65,\\\"view_read_ms\\\":4431,\\\"view_read_ms_per_100ch\\\":6817,\\\"seq_read_ms_raw\\\":1528,\\\"seq_read_ms_active\\\":1528,\\\"seq_read_ms_per_100ch\\\":2351,\\\"first_seen_ms\\\":4051,\\\"first_interaction_ms\\\":8482,\\\"value_path\\\":[{\\\"ms\\\":8482,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":623,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":10,\\\"index\\\":11,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":81,\\\"view_read_ms\\\":4286,\\\"view_read_ms_per_100ch\\\":5291,\\\"seq_read_ms_raw\\\":1963,\\\"seq_read_ms_active\\\":1963,\\\"seq_read_ms_per_100ch\\\":2423,\\\"first_seen_ms\\\":6159,\\\"first_interaction_ms\\\":10445,\\\"value_path\\\":[{\\\"ms\\\":10445,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":374,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":46,\\\"index\\\":12,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":36,\\\"view_read_ms\\\":1079,\\\"view_read_ms_per_100ch\\\":2997,\\\"seq_read_ms_raw\\\":388,\\\"seq_read_ms_active\\\":388,\\\"seq_read_ms_per_100ch\\\":1078,\\\"first_seen_ms\\\":9754,\\\"first_interaction_ms\\\":10833,\\\"value_path\\\":[{\\\"ms\\\":10833,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":518,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":11,\\\"index\\\":13,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":79,\\\"view_read_ms\\\":1374,\\\"view_read_ms_per_100ch\\\":1739,\\\"seq_read_ms_raw\\\":380,\\\"seq_read_ms_active\\\":380,\\\"seq_read_ms_per_100ch\\\":481,\\\"first_seen_ms\\\":9839,\\\"first_interaction_ms\\\":11213,\\\"value_path\\\":[{\\\"ms\\\":11213,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":882,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":8,\\\"index\\\":14,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":56,\\\"view_read_ms\\\":2087,\\\"view_read_ms_per_100ch\\\":3727,\\\"seq_read_ms_raw\\\":752,\\\"seq_read_ms_active\\\":752,\\\"seq_read_ms_per_100ch\\\":1343,\\\"first_seen_ms\\\":9878,\\\"first_interaction_ms\\\":11965,\\\"value_path\\\":[{\\\"ms\\\":11965,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":267,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":9,\\\"index\\\":15,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":2750,\\\"view_read_ms_per_100ch\\\":3313,\\\"seq_read_ms_raw\\\":717,\\\"seq_read_ms_active\\\":717,\\\"seq_read_ms_per_100ch\\\":864,\\\"first_seen_ms\\\":9932,\\\"first_interaction_ms\\\":12682,\\\"value_path\\\":[{\\\"ms\\\":12682,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":994,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":9,\\\"user_id\\\":57,\\\"target_id\\\":55,\\\"relation_type\\\":\\\"ceo\\\",\\\"answers_count\\\":15,\\\"items_count_server\\\":15,\\\"items_count_client\\\":15,\\\"server_received_at\\\":\\\"2025-08-31T21:51:14+02:00\\\",\\\"client_started_at\\\":\\\"2025-08-31T19:50:58.321Z\\\",\\\"client_finished_at\\\":\\\"2025-08-31T19:51:13.457Z\\\",\\\"measurement_uuid\\\":\\\"b5d7a1ce-0a17-4afb-a4c7-3dbdbab57013\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":15,\\\"value_counts\\\":{\\\"5\\\":8,\\\"6\\\":7},\\\"dominant_value\\\":5,\\\"dominant_share\\\":0.53300000000000002930988785010413266718387603759765625,\\\"extremes_share\\\":0,\\\"all_same_value\\\":false,\\\"mean_percent\\\":78.099999999999994315658113919198513031005859375,\\\"stddev_percent\\\":7.12999999999999989341858963598497211933135986328125},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":0.8569999999999999840127884453977458178997039794921875,\\\"fast_clicks_p1000\\\":0.6430000000000000159872115546022541821002960205078125,\\\"pace_cv\\\":0.61438151370220273062017213305807672441005706787109375,\\\"pace_median_ms\\\":665.5,\\\"pace_iqr_ms\\\":[382,1059],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.333000000000000018207657603852567262947559356689453125,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":1,\\\"assessment_span\\\":\\\"current_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":77,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":0,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"message\\\":\\\"Nincs kor\\u00e1bbi AI-\\u00e9rt\\u00e9kel\\u00e9s. \\u00daj felhaszn\\u00e1l\\u00f3 \\u2013 legy\\u00fcnk k\\u00edm\\u00e9letesek.\\\"}}\"'),
(9,57,56,'2025-08-31 21:59:46','{\"trust_score\":9,\"trust_index\":45,\"flags\":[\"one_click_fast_read\",\"too_fast\",\"fast_read\"],\"rationale\":\"The submission shows a full set of answers (12/12) with consistent counts and no mismatches, which is positive. However, the user has a very high one-click rate (1.0), indicating all responses were likely submitted with minimal interaction or changes, which strongly suggests low engagement. The average time per item (1078ms) is below the historical median (~989ms median with large variability); the pace CV is moderate, but there is a flagged \'too_fast_burst\' and suspicious one-click pattern. The high short-read rate (~41.7%) further supports fast, possibly superficial responses. The baseline is unavailable, so no baseline bias adjustment. Considering the guidance is \'be_kind\' and the history median trust score for CEO relations is 12, lowering trust score due to suspicious fast and uniform\",\"relation_type\":\"ceo\",\"target_id\":56,\"ai_timestamp\":\"2025-08-31T21:59:55+02:00\",\"features_snapshot\":{\"avg_ms_per_item\":1078,\"uniform_ratio\":0.416999999999999981792342396147432737052440643310546875,\"entropy\":null,\"zigzag_index\":0.299999999999999988897769753748434595763683319091796875,\"fast_pass_rate\":0.90000000000000002220446049250313080847263336181640625,\"device_type\":\"desktop\",\"pace_cv\":0.4939999999999999946709294817992486059665679931640625,\"pace_median_ms\":610,\"pace_iqr_ms\":[396,924],\"one_click_rate\":1,\"active_ratio\":1,\"short_read_rate\":0.416999999999999981792342396147432737052440643310546875,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":646,\"fast_read_rate_100ch\":0},\"baseline_echo\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"b9b3de4f-13da-470a-a3bb-3477a1f9a629\\\",\\\"started_at\\\":\\\"2025-08-31T19:59:32.904Z\\\",\\\"finished_at\\\":\\\"2025-08-31T19:59:45.841Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":2560,\\\"viewport_h\\\":945},\\\"items_count\\\":12,\\\"display_order\\\":[41,32,50,34,25,22,23,24,45,40,39,44],\\\"total_ms\\\":12937,\\\"visible_ms\\\":12937,\\\"active_ms\\\":12937,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":12,\\\"keydowns\\\":0,\\\"scrolls\\\":181,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":12},\\\"items\\\":[{\\\"question_id\\\":41,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":39,\\\"view_read_ms\\\":1021,\\\"view_read_ms_per_100ch\\\":2618,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":1041,\\\"value_path\\\":[{\\\"ms\\\":1041,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":440,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":32,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":102,\\\"view_read_ms\\\":1619,\\\"view_read_ms_per_100ch\\\":1587,\\\"seq_read_ms_raw\\\":598,\\\"seq_read_ms_active\\\":598,\\\"seq_read_ms_per_100ch\\\":586,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":1639,\\\"value_path\\\":[{\\\"ms\\\":1639,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1226,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":50,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":60,\\\"view_read_ms\\\":2292,\\\"view_read_ms_per_100ch\\\":3820,\\\"seq_read_ms_raw\\\":673,\\\"seq_read_ms_active\\\":673,\\\"seq_read_ms_per_100ch\\\":1122,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":2312,\\\"value_path\\\":[{\\\"ms\\\":2312,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":681,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":34,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":71,\\\"view_read_ms\\\":2666,\\\"view_read_ms_per_100ch\\\":3755,\\\"seq_read_ms_raw\\\":374,\\\"seq_read_ms_active\\\":374,\\\"seq_read_ms_per_100ch\\\":527,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":2686,\\\"value_path\\\":[{\\\"ms\\\":2686,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":564,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":25,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":81,\\\"view_read_ms\\\":1008,\\\"view_read_ms_per_100ch\\\":1244,\\\"seq_read_ms_raw\\\":1304,\\\"seq_read_ms_active\\\":1304,\\\"seq_read_ms_per_100ch\\\":1610,\\\"first_seen_ms\\\":2982,\\\"first_interaction_ms\\\":3990,\\\"value_path\\\":[{\\\"ms\\\":3990,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":355,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":22,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":116,\\\"view_read_ms\\\":1584,\\\"view_read_ms_per_100ch\\\":1366,\\\"seq_read_ms_raw\\\":622,\\\"seq_read_ms_active\\\":622,\\\"seq_read_ms_per_100ch\\\":536,\\\"first_seen_ms\\\":3028,\\\"first_interaction_ms\\\":4612,\\\"value_path\\\":[{\\\"ms\\\":4612,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1320,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":23,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":68,\\\"view_read_ms\\\":2104,\\\"view_read_ms_per_100ch\\\":3094,\\\"seq_read_ms_raw\\\":558,\\\"seq_read_ms_active\\\":558,\\\"seq_read_ms_per_100ch\\\":821,\\\"first_seen_ms\\\":3066,\\\"first_interaction_ms\\\":5170,\\\"value_path\\\":[{\\\"ms\\\":5170,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":480,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":24,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":2101,\\\"view_read_ms_per_100ch\\\":2531,\\\"seq_read_ms_raw\\\":396,\\\"seq_read_ms_active\\\":396,\\\"seq_read_ms_per_100ch\\\":477,\\\"first_seen_ms\\\":3465,\\\"first_interaction_ms\\\":5566,\\\"value_path\\\":[{\\\"ms\\\":5566,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":2869,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":45,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":143,\\\"view_read_ms\\\":624,\\\"view_read_ms_per_100ch\\\":436,\\\"seq_read_ms_raw\\\":924,\\\"seq_read_ms_active\\\":924,\\\"seq_read_ms_per_100ch\\\":646,\\\"first_seen_ms\\\":5866,\\\"first_interaction_ms\\\":6490,\\\"value_path\\\":[{\\\"ms\\\":6490,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":547,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":40,\\\"index\\\":10,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":104,\\\"view_read_ms\\\":1141,\\\"view_read_ms_per_100ch\\\":1097,\\\"seq_read_ms_raw\\\":545,\\\"seq_read_ms_active\\\":545,\\\"seq_read_ms_per_100ch\\\":524,\\\"first_seen_ms\\\":5894,\\\"first_interaction_ms\\\":7035,\\\"value_path\\\":[{\\\"ms\\\":7035,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":681,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":39,\\\"index\\\":11,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":43,\\\"view_read_ms\\\":2668,\\\"view_read_ms_per_100ch\\\":6205,\\\"seq_read_ms_raw\\\":512,\\\"seq_read_ms_active\\\":512,\\\"seq_read_ms_per_100ch\\\":1191,\\\"first_seen_ms\\\":5934,\\\"first_interaction_ms\\\":8602,\\\"value_path\\\":[{\\\"ms\\\":8602,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":693,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":44,\\\"index\\\":12,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":88,\\\"view_read_ms\\\":823,\\\"view_read_ms_per_100ch\\\":935,\\\"seq_read_ms_raw\\\":1055,\\\"seq_read_ms_active\\\":1055,\\\"seq_read_ms_per_100ch\\\":1199,\\\"first_seen_ms\\\":7267,\\\"first_interaction_ms\\\":8090,\\\"value_path\\\":[{\\\"ms\\\":8090,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":1028,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":9,\\\"user_id\\\":57,\\\"target_id\\\":56,\\\"relation_type\\\":\\\"ceo\\\",\\\"answers_count\\\":12,\\\"items_count_server\\\":12,\\\"items_count_client\\\":12,\\\"server_received_at\\\":\\\"2025-08-31T21:59:46+02:00\\\",\\\"client_started_at\\\":\\\"2025-08-31T19:59:32.904Z\\\",\\\"client_finished_at\\\":\\\"2025-08-31T19:59:45.841Z\\\",\\\"measurement_uuid\\\":\\\"b9b3de4f-13da-470a-a3bb-3477a1f9a629\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":12,\\\"value_counts\\\":{\\\"5\\\":5,\\\"6\\\":4,\\\"4\\\":2,\\\"7\\\":1},\\\"dominant_value\\\":5,\\\"dominant_share\\\":0.416999999999999981792342396147432737052440643310546875,\\\"extremes_share\\\":0.08300000000000000432986979603811050765216350555419921875,\\\"all_same_value\\\":false,\\\"mean_percent\\\":76.18999999999999772626324556767940521240234375,\\\"stddev_percent\\\":12.1400000000000005684341886080801486968994140625},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":0.90000000000000002220446049250313080847263336181640625,\\\"fast_clicks_p1000\\\":0.8000000000000000444089209850062616169452667236328125,\\\"pace_cv\\\":0.49403262842074735505804028434795327484607696533203125,\\\"pace_median_ms\\\":610,\\\"pace_iqr_ms\\\":[396,924],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.416999999999999981792342396147432737052440643310546875,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":0,\\\"assessment_span\\\":\\\"previous_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":77.6700000000000017053025658242404460906982421875,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":1,\\\"window_days\\\":0,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"relation_mix\\\":{\\\"ceo\\\":1},\\\"device_mix\\\":{\\\"desktop\\\":1},\\\"trust_summary\\\":{\\\"median\\\":12,\\\"iqr\\\":[12,12],\\\"low_rate\\\":0,\\\"high_rate\\\":0,\\\"trend\\\":\\\"flat\\\"},\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":989,\\\"iqr\\\":[989,989],\\\"coverage\\\":1},\\\"uniform_ratio\\\":{\\\"median\\\":0.6670000000000000373034936274052597582340240478515625,\\\"iqr\\\":[0.6670000000000000373034936274052597582340240478515625,0.6670000000000000373034936274052597582340240478515625],\\\"coverage\\\":1},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.5,\\\"iqr\\\":[0.5,0.5],\\\"coverage\\\":1},\\\"fast_pass_rate\\\":{\\\"median\\\":1,\\\"iqr\\\":[1,1],\\\"coverage\\\":1}},\\\"by_relation\\\":{\\\"ceo\\\":{\\\"n\\\":1,\\\"trust_median\\\":12,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]}},\\\"for_current_target\\\":null}}\"'),
(9,57,57,'2025-08-31 22:18:47','{\"trust_score\":10,\"trust_index\":50,\"flags\":[\"too_fast\",\"one_click_fast_read\"],\"rationale\":\"Current submission has one_click_rate=1.0 and fast_pass_rate=1.0 indicating rapid completion and no multiple changes, combined with reading_speed_median_100ch=668 which does not meet fast_read threshold (>400), so fast_read flag not set, but one_click_fast_read applies due to one_click_rate=1 and fast_pass_rate=1 indicating low-effort response pattern. Content_stats show dominant_share=0.556 and uniform_ratio=0.556 indicate moderate uniform responding but no extremes_only or too_uniform threshold met. Zigzag_index=0.143 is low, pace_cv=0.538 is moderate-high variability, so no suspicious_pattern. Baseline not available, so no baseline adjustment. History_digest shows consistent one_click_fast_read and too_fast in past assessments (rate=1), trend up but with scores median 9, supporting low/\",\"relation_type\":\"self\",\"target_id\":57,\"ai_timestamp\":\"2025-08-31T22:19:00+02:00\",\"features_snapshot\":{\"avg_ms_per_item\":987,\"uniform_ratio\":0.55600000000000004973799150320701301097869873046875,\"entropy\":null,\"zigzag_index\":0.1429999999999999882316359389733406715095043182373046875,\"fast_pass_rate\":1,\"device_type\":\"desktop\",\"pace_cv\":0.5380000000000000337507799486047588288784027099609375,\"pace_median_ms\":477,\"pace_iqr_ms\":[404,978],\"one_click_rate\":1,\"active_ratio\":1,\"short_read_rate\":0.444000000000000005773159728050814010202884674072265625,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":668,\"fast_read_rate_100ch\":0},\"baseline_echo\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"1b2f6cba-9500-4b8c-94d6-87fad586719c\\\",\\\"started_at\\\":\\\"2025-08-31T20:18:38.487Z\\\",\\\"finished_at\\\":\\\"2025-08-31T20:18:47.373Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":2560,\\\"viewport_h\\\":945},\\\"items_count\\\":9,\\\"display_order\\\":[17,18,19,21,25,24,23,22,51],\\\"total_ms\\\":8886,\\\"visible_ms\\\":8886,\\\"active_ms\\\":8886,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":9,\\\"keydowns\\\":0,\\\"scrolls\\\":66,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":9},\\\"items\\\":[{\\\"question_id\\\":17,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":82,\\\"view_read_ms\\\":914,\\\"view_read_ms_per_100ch\\\":1115,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":934,\\\"value_path\\\":[{\\\"ms\\\":934,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":267,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":18,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":91,\\\"view_read_ms\\\":1325,\\\"view_read_ms_per_100ch\\\":1456,\\\"seq_read_ms_raw\\\":411,\\\"seq_read_ms_active\\\":411,\\\"seq_read_ms_per_100ch\\\":452,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":1345,\\\"value_path\\\":[{\\\"ms\\\":1345,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1115,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":19,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":97,\\\"view_read_ms\\\":1721,\\\"view_read_ms_per_100ch\\\":1774,\\\"seq_read_ms_raw\\\":396,\\\"seq_read_ms_active\\\":396,\\\"seq_read_ms_per_100ch\\\":408,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":1741,\\\"value_path\\\":[{\\\"ms\\\":1741,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":579,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":21,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":68,\\\"view_read_ms\\\":2699,\\\"view_read_ms_per_100ch\\\":3969,\\\"seq_read_ms_raw\\\":978,\\\"seq_read_ms_active\\\":978,\\\"seq_read_ms_per_100ch\\\":1438,\\\"first_seen_ms\\\":20,\\\"first_interaction_ms\\\":2719,\\\"value_path\\\":[{\\\"ms\\\":2719,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":327,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":25,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":87,\\\"view_read_ms\\\":1199,\\\"view_read_ms_per_100ch\\\":1378,\\\"seq_read_ms_raw\\\":520,\\\"seq_read_ms_active\\\":520,\\\"seq_read_ms_per_100ch\\\":598,\\\"first_seen_ms\\\":2040,\\\"first_interaction_ms\\\":3239,\\\"value_path\\\":[{\\\"ms\\\":3239,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":573,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":24,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":76,\\\"view_read_ms\\\":2643,\\\"view_read_ms_per_100ch\\\":3478,\\\"seq_read_ms_raw\\\":1490,\\\"seq_read_ms_active\\\":1490,\\\"seq_read_ms_per_100ch\\\":1961,\\\"first_seen_ms\\\":2086,\\\"first_interaction_ms\\\":4729,\\\"value_path\\\":[{\\\"ms\\\":4729,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1075,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":23,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":63,\\\"view_read_ms\\\":3008,\\\"view_read_ms_per_100ch\\\":4775,\\\"seq_read_ms_raw\\\":404,\\\"seq_read_ms_active\\\":404,\\\"seq_read_ms_per_100ch\\\":641,\\\"first_seen_ms\\\":2125,\\\"first_interaction_ms\\\":5133,\\\"value_path\\\":[{\\\"ms\\\":5133,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1118,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":22,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":133,\\\"view_read_ms\\\":2104,\\\"view_read_ms_per_100ch\\\":1582,\\\"seq_read_ms_raw\\\":924,\\\"seq_read_ms_active\\\":924,\\\"seq_read_ms_per_100ch\\\":695,\\\"first_seen_ms\\\":3953,\\\"first_interaction_ms\\\":6057,\\\"value_path\\\":[{\\\"ms\\\":6057,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":307,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":51,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":41,\\\"view_read_ms\\\":1058,\\\"view_read_ms_per_100ch\\\":2580,\\\"seq_read_ms_raw\\\":434,\\\"seq_read_ms_active\\\":434,\\\"seq_read_ms_per_100ch\\\":1059,\\\"first_seen_ms\\\":5433,\\\"first_interaction_ms\\\":6491,\\\"value_path\\\":[{\\\"ms\\\":6491,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1154,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":9,\\\"user_id\\\":57,\\\"target_id\\\":57,\\\"relation_type\\\":\\\"self\\\",\\\"answers_count\\\":9,\\\"items_count_server\\\":9,\\\"items_count_client\\\":9,\\\"server_received_at\\\":\\\"2025-08-31T22:18:47+02:00\\\",\\\"client_started_at\\\":\\\"2025-08-31T20:18:38.487Z\\\",\\\"client_finished_at\\\":\\\"2025-08-31T20:18:47.373Z\\\",\\\"measurement_uuid\\\":\\\"1b2f6cba-9500-4b8c-94d6-87fad586719c\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":9,\\\"value_counts\\\":{\\\"5\\\":5,\\\"6\\\":3,\\\"4\\\":1},\\\"dominant_value\\\":5,\\\"dominant_share\\\":0.55600000000000004973799150320701301097869873046875,\\\"extremes_share\\\":0,\\\"all_same_value\\\":false,\\\"mean_percent\\\":74.599999999999994315658113919198513031005859375,\\\"stddev_percent\\\":8.980000000000000426325641456060111522674560546875},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":1,\\\"fast_clicks_p1000\\\":0.875,\\\"pace_cv\\\":0.53818656833220901436476424350985325872898101806640625,\\\"pace_median_ms\\\":477,\\\"pace_iqr_ms\\\":[404,978],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.444000000000000005773159728050814010202884674072265625,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":0,\\\"assessment_span\\\":\\\"previous_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":73.6700000000000017053025658242404460906982421875,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":2,\\\"window_days\\\":0,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"relation_mix\\\":{\\\"ceo\\\":2},\\\"device_mix\\\":{\\\"desktop\\\":2},\\\"trust_summary\\\":{\\\"median\\\":10.5,\\\"iqr\\\":[9,12],\\\"low_rate\\\":0,\\\"high_rate\\\":0,\\\"trend\\\":\\\"up\\\"},\\\"flags_top\\\":[{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"fast_read\\\",\\\"rate\\\":0.5}],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":1033.5,\\\"iqr\\\":[989,1078],\\\"coverage\\\":2},\\\"uniform_ratio\\\":{\\\"median\\\":0.5420000000000000373034936274052597582340240478515625,\\\"iqr\\\":[0.416999999999999981792342396147432737052440643310546875,0.6670000000000000373034936274052597582340240478515625],\\\"coverage\\\":2},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.40000000000000002220446049250313080847263336181640625,\\\"iqr\\\":[0.299999999999999988897769753748434595763683319091796875,0.5],\\\"coverage\\\":2},\\\"fast_pass_rate\\\":{\\\"median\\\":0.9499999999999999555910790149937383830547332763671875,\\\"iqr\\\":[0.90000000000000002220446049250313080847263336181640625,1],\\\"coverage\\\":2}},\\\"by_relation\\\":{\\\"ceo\\\":{\\\"n\\\":2,\\\"trust_median\\\":9,\\\"flags_top\\\":[{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1}]}},\\\"for_current_target\\\":null}}\"'),
(9,57,58,'2025-08-31 21:54:57','{\"trust_score\":12,\"trust_index\":70,\"flags\":[\"too_fast\",\"one_click_fast_read\"],\"rationale\":\"The submission has a baseline unavailable, so no delta_mean adjustment. The user answered all items with one-click (one_click_all true) and very fast pace (too_fast_burst true), raising suspicion. However, content shows reasonable variability and lacks extremes only pattern. Given \'be_kind\' guidance and no prior history, the trust score is moderately reduced but not severely. The trust_index reflects this moderate confidence.\",\"relation_type\":\"ceo\",\"target_id\":58,\"ai_timestamp\":\"2025-08-31T21:58:00+02:00\",\"features_snapshot\":{\"avg_ms_per_item\":989,\"uniform_ratio\":0.6670000000000000373034936274052597582340240478515625,\"entropy\":null,\"zigzag_index\":0.5,\"fast_pass_rate\":1,\"device_type\":\"desktop\",\"pace_cv\":0.50300000000000000266453525910037569701671600341796875,\"pace_median_ms\":452,\"pace_iqr_ms\":[396,868],\"one_click_rate\":1,\"active_ratio\":1,\"short_read_rate\":0.416999999999999981792342396147432737052440643310546875,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":679,\"fast_read_rate_100ch\":0},\"anyOf\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"2dd703ce-25b9-42cd-974a-1985f2a3d73f\\\",\\\"started_at\\\":\\\"2025-08-31T19:54:45.257Z\\\",\\\"finished_at\\\":\\\"2025-08-31T19:54:57.129Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":2560,\\\"viewport_h\\\":945},\\\"items_count\\\":12,\\\"display_order\\\":[46,10,8,9,11,49,48,15,42,43,27,51],\\\"total_ms\\\":11871,\\\"visible_ms\\\":11871,\\\"active_ms\\\":11871,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":12,\\\"keydowns\\\":0,\\\"scrolls\\\":64,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":12},\\\"items\\\":[{\\\"question_id\\\":46,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":36,\\\"view_read_ms\\\":1231,\\\"view_read_ms_per_100ch\\\":3419,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":23,\\\"first_interaction_ms\\\":1254,\\\"value_path\\\":[{\\\"ms\\\":1254,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":400,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":10,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":81,\\\"view_read_ms\\\":1658,\\\"view_read_ms_per_100ch\\\":2047,\\\"seq_read_ms_raw\\\":427,\\\"seq_read_ms_active\\\":427,\\\"seq_read_ms_per_100ch\\\":527,\\\"first_seen_ms\\\":23,\\\"first_interaction_ms\\\":1681,\\\"value_path\\\":[{\\\"ms\\\":1681,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":770,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":8,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":56,\\\"view_read_ms\\\":2038,\\\"view_read_ms_per_100ch\\\":3639,\\\"seq_read_ms_raw\\\":380,\\\"seq_read_ms_active\\\":380,\\\"seq_read_ms_per_100ch\\\":679,\\\"first_seen_ms\\\":23,\\\"first_interaction_ms\\\":2061,\\\"value_path\\\":[{\\\"ms\\\":2061,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1032,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":9,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":2792,\\\"view_read_ms_per_100ch\\\":3364,\\\"seq_read_ms_raw\\\":754,\\\"seq_read_ms_active\\\":754,\\\"seq_read_ms_per_100ch\\\":908,\\\"first_seen_ms\\\":23,\\\"first_interaction_ms\\\":2815,\\\"value_path\\\":[{\\\"ms\\\":2815,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":409,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":11,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":79,\\\"view_read_ms\\\":1010,\\\"view_read_ms_per_100ch\\\":1278,\\\"seq_read_ms_raw\\\":434,\\\"seq_read_ms_active\\\":434,\\\"seq_read_ms_per_100ch\\\":549,\\\"first_seen_ms\\\":2239,\\\"first_interaction_ms\\\":3249,\\\"value_path\\\":[{\\\"ms\\\":3249,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":680,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":49,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":43,\\\"view_read_ms\\\":2280,\\\"view_read_ms_per_100ch\\\":5302,\\\"seq_read_ms_raw\\\":1430,\\\"seq_read_ms_active\\\":1430,\\\"seq_read_ms_per_100ch\\\":3326,\\\"first_seen_ms\\\":2399,\\\"first_interaction_ms\\\":4679,\\\"value_path\\\":[{\\\"ms\\\":4679,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":262,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":48,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":85,\\\"view_read_ms\\\":1664,\\\"view_read_ms_per_100ch\\\":1958,\\\"seq_read_ms_raw\\\":458,\\\"seq_read_ms_active\\\":458,\\\"seq_read_ms_per_100ch\\\":539,\\\"first_seen_ms\\\":3473,\\\"first_interaction_ms\\\":5137,\\\"value_path\\\":[{\\\"ms\\\":5137,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":780,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":15,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":98,\\\"view_read_ms\\\":2040,\\\"view_read_ms_per_100ch\\\":2082,\\\"seq_read_ms_raw\\\":396,\\\"seq_read_ms_active\\\":396,\\\"seq_read_ms_per_100ch\\\":404,\\\"first_seen_ms\\\":3493,\\\"first_interaction_ms\\\":5533,\\\"value_path\\\":[{\\\"ms\\\":5533,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":992,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":42,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":47,\\\"view_read_ms\\\":2842,\\\"view_read_ms_per_100ch\\\":6047,\\\"seq_read_ms_raw\\\":868,\\\"seq_read_ms_active\\\":868,\\\"seq_read_ms_per_100ch\\\":1847,\\\"first_seen_ms\\\":3559,\\\"first_interaction_ms\\\":6401,\\\"value_path\\\":[{\\\"ms\\\":6401,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":308,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":43,\\\"index\\\":10,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":85,\\\"view_read_ms\\\":1082,\\\"view_read_ms_per_100ch\\\":1273,\\\"seq_read_ms_raw\\\":452,\\\"seq_read_ms_active\\\":452,\\\"seq_read_ms_per_100ch\\\":532,\\\"first_seen_ms\\\":5771,\\\"first_interaction_ms\\\":6853,\\\"value_path\\\":[{\\\"ms\\\":6853,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":533,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":27,\\\"index\\\":11,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":54,\\\"view_read_ms\\\":1466,\\\"view_read_ms_per_100ch\\\":2715,\\\"seq_read_ms_raw\\\":410,\\\"seq_read_ms_active\\\":410,\\\"seq_read_ms_per_100ch\\\":759,\\\"first_seen_ms\\\":5797,\\\"first_interaction_ms\\\":7263,\\\"value_path\\\":[{\\\"ms\\\":7263,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":875,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":51,\\\"index\\\":12,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":26,\\\"view_read_ms\\\":744,\\\"view_read_ms_per_100ch\\\":2862,\\\"seq_read_ms_raw\\\":932,\\\"seq_read_ms_active\\\":932,\\\"seq_read_ms_per_100ch\\\":3585,\\\"first_seen_ms\\\":7451,\\\"first_interaction_ms\\\":8195,\\\"value_path\\\":[{\\\"ms\\\":8195,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":639,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":9,\\\"user_id\\\":57,\\\"target_id\\\":58,\\\"relation_type\\\":\\\"ceo\\\",\\\"answers_count\\\":12,\\\"items_count_server\\\":12,\\\"items_count_client\\\":12,\\\"server_received_at\\\":\\\"2025-08-31T21:54:57+02:00\\\",\\\"client_started_at\\\":\\\"2025-08-31T19:54:45.257Z\\\",\\\"client_finished_at\\\":\\\"2025-08-31T19:54:57.129Z\\\",\\\"measurement_uuid\\\":\\\"2dd703ce-25b9-42cd-974a-1985f2a3d73f\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":12,\\\"value_counts\\\":{\\\"6\\\":8,\\\"5\\\":3,\\\"7\\\":1},\\\"dominant_value\\\":6,\\\"dominant_share\\\":0.6670000000000000373034936274052597582340240478515625,\\\"extremes_share\\\":0.08300000000000000432986979603811050765216350555419921875,\\\"all_same_value\\\":false,\\\"mean_percent\\\":83.3299999999999982946974341757595539093017578125,\\\"stddev_percent\\\":7.9000000000000003552713678800500929355621337890625},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":1,\\\"fast_clicks_p1000\\\":0.9090000000000000301980662698042578995227813720703125,\\\"pace_cv\\\":0.50337219561112445109785085151088424026966094970703125,\\\"pace_median_ms\\\":452,\\\"pace_iqr_ms\\\":[396,868],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.416999999999999981792342396147432737052440643310546875,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":0,\\\"assessment_span\\\":\\\"previous_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":86.5,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":0,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"message\\\":\\\"Nincs kor\\u00e1bbi AI-\\u00e9rt\\u00e9kel\\u00e9s. \\u00daj felhaszn\\u00e1l\\u00f3 \\u2013 legy\\u00fcnk k\\u00edm\\u00e9letesek.\\\"}}\"'),
(13,43,43,'2025-09-03 15:05:00','{\"trust_score\":7,\"trust_index\":35,\"flags\":[\"too_fast\",\"one_click_fast_read\"],\"rationale\":\"fast_pass_rate=1.0 and one_click_rate=1.0 with one_click_all=true indicate extremely low effort and too fast answering. reading_speed_median_100ch=1063 and short_read_rate=0.5 do not qualify as fast_read, so fast_read flag not set. content_stats shows dominant_share=0.5 and uniform_ratio=0.5 (moderate) with zigzag_index=0.5, indicating no extreme uniformity or randomness. baseline unavailable, so no adjustment. history tier is cold_start with trust_median=18 but current data show strong low-effort patterns, justifying a moderate penalty under \'be_kind\' guidance.\",\"relation_type\":\"self\",\"target_id\":43,\"ai_timestamp\":\"2025-09-03T15:10:00+02:00\",\"features_snapshot\":{\"avg_ms_per_item\":916,\"uniform_ratio\":0.5,\"entropy\":null,\"zigzag_index\":0.5,\"fast_pass_rate\":1,\"device_type\":\"desktop\",\"pace_cv\":0.36999999999999999555910790149937383830547332763671875,\"pace_median_ms\":795,\"pace_iqr_ms\":[342,829],\"one_click_rate\":1,\"active_ratio\":1,\"short_read_rate\":0.5,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":1063,\"fast_read_rate_100ch\":0},\"baseline_echo\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"4d7acfbd-a042-446c-905d-d888db63c0b1\\\",\\\"started_at\\\":\\\"2025-09-03T13:04:51.274Z\\\",\\\"finished_at\\\":\\\"2025-09-03T13:04:58.605Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":1278,\\\"viewport_h\\\":944},\\\"items_count\\\":8,\\\"display_order\\\":[15,48,49,47,36,35,38,37],\\\"total_ms\\\":7331,\\\"visible_ms\\\":7331,\\\"active_ms\\\":7331,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":8,\\\"keydowns\\\":0,\\\"scrolls\\\":53,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":8},\\\"items\\\":[{\\\"question_id\\\":15,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":104,\\\"view_read_ms\\\":680,\\\"view_read_ms_per_100ch\\\":654,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":16,\\\"first_interaction_ms\\\":696,\\\"value_path\\\":[{\\\"ms\\\":696,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":795,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":48,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":76,\\\"view_read_ms\\\":1509,\\\"view_read_ms_per_100ch\\\":1986,\\\"seq_read_ms_raw\\\":829,\\\"seq_read_ms_active\\\":829,\\\"seq_read_ms_per_100ch\\\":1091,\\\"first_seen_ms\\\":16,\\\"first_interaction_ms\\\":1525,\\\"value_path\\\":[{\\\"ms\\\":1525,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":773,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":49,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":44,\\\"view_read_ms\\\":1851,\\\"view_read_ms_per_100ch\\\":4207,\\\"seq_read_ms_raw\\\":342,\\\"seq_read_ms_active\\\":342,\\\"seq_read_ms_per_100ch\\\":777,\\\"first_seen_ms\\\":16,\\\"first_interaction_ms\\\":1867,\\\"value_path\\\":[{\\\"ms\\\":1867,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":619,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":47,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":84,\\\"view_read_ms\\\":801,\\\"view_read_ms_per_100ch\\\":954,\\\"seq_read_ms_raw\\\":1096,\\\"seq_read_ms_active\\\":1096,\\\"seq_read_ms_per_100ch\\\":1305,\\\"first_seen_ms\\\":2162,\\\"first_interaction_ms\\\":2963,\\\"value_path\\\":[{\\\"ms\\\":2963,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":795,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":36,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":69,\\\"view_read_ms\\\":1266,\\\"view_read_ms_per_100ch\\\":1835,\\\"seq_read_ms_raw\\\":558,\\\"seq_read_ms_active\\\":558,\\\"seq_read_ms_per_100ch\\\":809,\\\"first_seen_ms\\\":2255,\\\"first_interaction_ms\\\":3521,\\\"value_path\\\":[{\\\"ms\\\":3521,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":671,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":35,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":48,\\\"view_read_ms\\\":2007,\\\"view_read_ms_per_100ch\\\":4181,\\\"seq_read_ms_raw\\\":795,\\\"seq_read_ms_active\\\":795,\\\"seq_read_ms_per_100ch\\\":1656,\\\"first_seen_ms\\\":2309,\\\"first_interaction_ms\\\":4316,\\\"value_path\\\":[{\\\"ms\\\":4316,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":315,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":38,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":69,\\\"view_read_ms\\\":913,\\\"view_read_ms_per_100ch\\\":1323,\\\"seq_read_ms_raw\\\":371,\\\"seq_read_ms_active\\\":371,\\\"seq_read_ms_per_100ch\\\":538,\\\"first_seen_ms\\\":3774,\\\"first_interaction_ms\\\":4687,\\\"value_path\\\":[{\\\"ms\\\":4687,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":640,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":37,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":76,\\\"view_read_ms\\\":1680,\\\"view_read_ms_per_100ch\\\":2211,\\\"seq_read_ms_raw\\\":808,\\\"seq_read_ms_active\\\":808,\\\"seq_read_ms_per_100ch\\\":1063,\\\"first_seen_ms\\\":3815,\\\"first_interaction_ms\\\":5495,\\\"value_path\\\":[{\\\"ms\\\":5495,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":1444,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":13,\\\"user_id\\\":43,\\\"target_id\\\":43,\\\"relation_type\\\":\\\"self\\\",\\\"answers_count\\\":8,\\\"items_count_server\\\":8,\\\"items_count_client\\\":8,\\\"server_received_at\\\":\\\"2025-09-03T15:05:00+02:00\\\",\\\"client_started_at\\\":\\\"2025-09-03T13:04:51.274Z\\\",\\\"client_finished_at\\\":\\\"2025-09-03T13:04:58.605Z\\\",\\\"measurement_uuid\\\":\\\"4d7acfbd-a042-446c-905d-d888db63c0b1\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":8,\\\"value_counts\\\":{\\\"6\\\":4,\\\"4\\\":2,\\\"5\\\":1,\\\"7\\\":1},\\\"dominant_value\\\":6,\\\"dominant_share\\\":0.5,\\\"extremes_share\\\":0.125,\\\"all_same_value\\\":false,\\\"mean_percent\\\":78.56999999999999317878973670303821563720703125,\\\"stddev_percent\\\":14.28999999999999914734871708787977695465087890625},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":true,\\\"fast_clicks_p1500\\\":1,\\\"fast_clicks_p1000\\\":0.8569999999999999840127884453977458178997039794921875,\\\"pace_cv\\\":0.369526885521175685322958770484547130763530731201171875,\\\"pace_median_ms\\\":795,\\\"pace_iqr_ms\\\":[342,829],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.5,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":0,\\\"assessment_span\\\":\\\"previous_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":76,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":1,\\\"window_days\\\":0,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"relation_mix\\\":{\\\"colleague\\\":1},\\\"device_mix\\\":{\\\"desktop\\\":1},\\\"trust_summary\\\":{\\\"median\\\":18,\\\"iqr\\\":[18,18],\\\"low_rate\\\":0,\\\"high_rate\\\":1,\\\"trend\\\":\\\"flat\\\"},\\\"flags_top\\\":[],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":1576,\\\"iqr\\\":[1576,1576],\\\"coverage\\\":1},\\\"uniform_ratio\\\":{\\\"median\\\":0.40000000000000002220446049250313080847263336181640625,\\\"iqr\\\":[0.40000000000000002220446049250313080847263336181640625,0.40000000000000002220446049250313080847263336181640625],\\\"coverage\\\":1},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.462000000000000021760371282653068192303180694580078125,\\\"iqr\\\":[0.462000000000000021760371282653068192303180694580078125,0.462000000000000021760371282653068192303180694580078125],\\\"coverage\\\":1},\\\"fast_pass_rate\\\":{\\\"median\\\":0,\\\"iqr\\\":[0,0],\\\"coverage\\\":1}},\\\"by_relation\\\":{\\\"colleague\\\":{\\\"n\\\":1,\\\"trust_median\\\":18,\\\"flags_top\\\":[]}},\\\"for_current_target\\\":null}}\"'),
(13,43,55,'2025-09-03 15:05:26','{\"trust_score\":10,\"trust_index\":50,\"flags\":[\"too_fast\",\"one_click_fast_read\"],\"rationale\":\"one_click_rate=1.00 and one_click_all=true with fast_pass_rate=1.00 indicate very fast, single-click responses, a low-effort pattern. Reading speed median 1064.5 > 400 and short_read_rate=0.133 < 0.60 so no fast_read flag. dominant_share=0.467 and uniform_ratio=0.467 are moderate, no extremes_only or too_uniform flags. pace_cv=0.413 indicates moderate pace variability which is positive evidence. baseline unavailable, so no adjustments from delta_mean. History for colleague relation shows an up trend and no flags top, so no negative adjustment from history. Overall strong evidence of low-effort rapid responding (too_fast and one_click_fast_read) with moderate pace variability. Under be_kind guidance, apply about -2 penalty for low-effort flags and +1 for moderate pace variability, resulting\",\"relation_type\":\"colleague\",\"target_id\":55,\"ai_timestamp\":\"2025-09-03T15:10:00Z\",\"features_snapshot\":{\"avg_ms_per_item\":880,\"uniform_ratio\":0.467000000000000026201263381153694353997707366943359375,\"entropy\":null,\"zigzag_index\":0.5380000000000000337507799486047588288784027099609375,\"fast_pass_rate\":1,\"device_type\":\"desktop\",\"pace_cv\":0.412999999999999978239628717346931807696819305419921875,\"pace_median_ms\":672,\"pace_iqr_ms\":[350,784],\"one_click_rate\":1,\"active_ratio\":1,\"short_read_rate\":0.13300000000000000710542735760100185871124267578125,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":1064.5,\"fast_read_rate_100ch\":0.1429999999999999882316359389733406715095043182373046875},\"baseline_echo\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"2cbada0e-5747-48ea-87b3-b9892d023433\\\",\\\"started_at\\\":\\\"2025-09-03T13:05:11.118Z\\\",\\\"finished_at\\\":\\\"2025-09-03T13:05:24.323Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":1278,\\\"viewport_h\\\":944},\\\"items_count\\\":15,\\\"display_order\\\":[28,26,29,1,4,6,5,3,13,7,10,9,8,46,11],\\\"total_ms\\\":13204,\\\"visible_ms\\\":13204,\\\"active_ms\\\":13204,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":15,\\\"keydowns\\\":0,\\\"scrolls\\\":100,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":15},\\\"items\\\":[{\\\"question_id\\\":28,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":97,\\\"view_read_ms\\\":1706,\\\"view_read_ms_per_100ch\\\":1759,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":16,\\\"first_interaction_ms\\\":1722,\\\"value_path\\\":[{\\\"ms\\\":1722,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":442,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":26,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":88,\\\"view_read_ms\\\":2265,\\\"view_read_ms_per_100ch\\\":2574,\\\"seq_read_ms_raw\\\":559,\\\"seq_read_ms_active\\\":559,\\\"seq_read_ms_per_100ch\\\":635,\\\"first_seen_ms\\\":16,\\\"first_interaction_ms\\\":2281,\\\"value_path\\\":[{\\\"ms\\\":2281,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":893,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":29,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":47,\\\"view_read_ms\\\":3033,\\\"view_read_ms_per_100ch\\\":6453,\\\"seq_read_ms_raw\\\":768,\\\"seq_read_ms_active\\\":768,\\\"seq_read_ms_per_100ch\\\":1634,\\\"first_seen_ms\\\":16,\\\"first_interaction_ms\\\":3049,\\\"value_path\\\":[{\\\"ms\\\":3049,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":776,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":1,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":4105,\\\"view_read_ms_per_100ch\\\":4946,\\\"seq_read_ms_raw\\\":1072,\\\"seq_read_ms_active\\\":1072,\\\"seq_read_ms_per_100ch\\\":1292,\\\"first_seen_ms\\\":16,\\\"first_interaction_ms\\\":4121,\\\"value_path\\\":[{\\\"ms\\\":4121,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1421,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":4,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":60,\\\"view_read_ms\\\":1703,\\\"view_read_ms_per_100ch\\\":2838,\\\"seq_read_ms_raw\\\":994,\\\"seq_read_ms_active\\\":994,\\\"seq_read_ms_per_100ch\\\":1657,\\\"first_seen_ms\\\":3412,\\\"first_interaction_ms\\\":5115,\\\"value_path\\\":[{\\\"ms\\\":5115,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":654,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":6,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":43,\\\"view_read_ms\\\":2155,\\\"view_read_ms_per_100ch\\\":5012,\\\"seq_read_ms_raw\\\":506,\\\"seq_read_ms_active\\\":506,\\\"seq_read_ms_per_100ch\\\":1177,\\\"first_seen_ms\\\":3466,\\\"first_interaction_ms\\\":5621,\\\"value_path\\\":[{\\\"ms\\\":5621,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":599,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":5,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":65,\\\"view_read_ms\\\":1737,\\\"view_read_ms_per_100ch\\\":2672,\\\"seq_read_ms_raw\\\":582,\\\"seq_read_ms_active\\\":582,\\\"seq_read_ms_per_100ch\\\":895,\\\"first_seen_ms\\\":4466,\\\"first_interaction_ms\\\":6203,\\\"value_path\\\":[{\\\"ms\\\":6203,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":534,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":3,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":88,\\\"view_read_ms\\\":2031,\\\"view_read_ms_per_100ch\\\":2308,\\\"seq_read_ms_raw\\\":334,\\\"seq_read_ms_active\\\":334,\\\"seq_read_ms_per_100ch\\\":380,\\\"first_seen_ms\\\":4506,\\\"first_interaction_ms\\\":6537,\\\"value_path\\\":[{\\\"ms\\\":6537,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":606,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":13,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":67,\\\"view_read_ms\\\":2753,\\\"view_read_ms_per_100ch\\\":4109,\\\"seq_read_ms_raw\\\":776,\\\"seq_read_ms_active\\\":776,\\\"seq_read_ms_per_100ch\\\":1158,\\\"first_seen_ms\\\":4560,\\\"first_interaction_ms\\\":7313,\\\"value_path\\\":[{\\\"ms\\\":7313,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":479,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":7,\\\"index\\\":10,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":76,\\\"view_read_ms\\\":1212,\\\"view_read_ms_per_100ch\\\":1595,\\\"seq_read_ms_raw\\\":738,\\\"seq_read_ms_active\\\":738,\\\"seq_read_ms_per_100ch\\\":971,\\\"first_seen_ms\\\":6839,\\\"first_interaction_ms\\\":8051,\\\"value_path\\\":[{\\\"ms\\\":8051,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":961,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":10,\\\"index\\\":11,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":81,\\\"view_read_ms\\\":1108,\\\"view_read_ms_per_100ch\\\":1368,\\\"seq_read_ms_raw\\\":1336,\\\"seq_read_ms_active\\\":1336,\\\"seq_read_ms_per_100ch\\\":1649,\\\"first_seen_ms\\\":8279,\\\"first_interaction_ms\\\":9387,\\\"value_path\\\":[{\\\"ms\\\":9387,\\\"v\\\":3}],\\\"last_value\\\":3,\\\"changes_count\\\":0,\\\"focus_ms\\\":253,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":9,\\\"index\\\":12,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":1686,\\\"view_read_ms_per_100ch\\\":2031,\\\"seq_read_ms_raw\\\":606,\\\"seq_read_ms_active\\\":606,\\\"seq_read_ms_per_100ch\\\":730,\\\"first_seen_ms\\\":8307,\\\"first_interaction_ms\\\":9993,\\\"value_path\\\":[{\\\"ms\\\":9993,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":841,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":8,\\\"index\\\":13,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":56,\\\"view_read_ms\\\":2011,\\\"view_read_ms_per_100ch\\\":3591,\\\"seq_read_ms_raw\\\":350,\\\"seq_read_ms_active\\\":350,\\\"seq_read_ms_per_100ch\\\":625,\\\"first_seen_ms\\\":8332,\\\"first_interaction_ms\\\":10343,\\\"value_path\\\":[{\\\"ms\\\":10343,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":789,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":46,\\\"index\\\":14,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":36,\\\"view_read_ms\\\":2527,\\\"view_read_ms_per_100ch\\\":7019,\\\"seq_read_ms_raw\\\":784,\\\"seq_read_ms_active\\\":784,\\\"seq_read_ms_per_100ch\\\":2178,\\\"first_seen_ms\\\":8600,\\\"first_interaction_ms\\\":11127,\\\"value_path\\\":[{\\\"ms\\\":11127,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":541,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":11,\\\"index\\\":15,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":79,\\\"view_read_ms\\\":2784,\\\"view_read_ms_per_100ch\\\":3524,\\\"seq_read_ms_raw\\\":297,\\\"seq_read_ms_active\\\":297,\\\"seq_read_ms_per_100ch\\\":376,\\\"first_seen_ms\\\":8640,\\\"first_interaction_ms\\\":11424,\\\"value_path\\\":[{\\\"ms\\\":11424,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":775,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":13,\\\"user_id\\\":43,\\\"target_id\\\":55,\\\"relation_type\\\":\\\"colleague\\\",\\\"answers_count\\\":15,\\\"items_count_server\\\":15,\\\"items_count_client\\\":15,\\\"server_received_at\\\":\\\"2025-09-03T15:05:26+02:00\\\",\\\"client_started_at\\\":\\\"2025-09-03T13:05:11.118Z\\\",\\\"client_finished_at\\\":\\\"2025-09-03T13:05:24.323Z\\\",\\\"measurement_uuid\\\":\\\"2cbada0e-5747-48ea-87b3-b9892d023433\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":15,\\\"value_counts\\\":{\\\"6\\\":7,\\\"5\\\":4,\\\"4\\\":2,\\\"7\\\":1,\\\"3\\\":1},\\\"dominant_value\\\":6,\\\"dominant_share\\\":0.467000000000000026201263381153694353997707366943359375,\\\"extremes_share\\\":0.067000000000000003996802888650563545525074005126953125,\\\"all_same_value\\\":false,\\\"mean_percent\\\":76.18999999999999772626324556767940521240234375,\\\"stddev_percent\\\":14.4399999999999995026200849679298698902130126953125},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":1,\\\"fast_clicks_p1000\\\":0.8569999999999999840127884453977458178997039794921875,\\\"pace_cv\\\":0.412973658849396907388751287726336158812046051025390625,\\\"pace_median_ms\\\":672,\\\"pace_iqr_ms\\\":[350,784],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.13300000000000000710542735760100185871124267578125,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":0,\\\"assessment_span\\\":\\\"previous_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":78.3299999999999982946974341757595539093017578125,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":2,\\\"window_days\\\":3,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"relation_mix\\\":{\\\"self\\\":1,\\\"colleague\\\":1},\\\"device_mix\\\":{\\\"desktop\\\":2},\\\"trust_summary\\\":{\\\"median\\\":12.5,\\\"iqr\\\":[7,18],\\\"low_rate\\\":0.5,\\\"high_rate\\\":0.5,\\\"trend\\\":\\\"up\\\"},\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":0.5},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":0.5}],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":1246,\\\"iqr\\\":[916,1576],\\\"coverage\\\":2},\\\"uniform_ratio\\\":{\\\"median\\\":0.450000000000000011102230246251565404236316680908203125,\\\"iqr\\\":[0.40000000000000002220446049250313080847263336181640625,0.5],\\\"coverage\\\":2},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.48099999999999998312461002569762058556079864501953125,\\\"iqr\\\":[0.462000000000000021760371282653068192303180694580078125,0.5],\\\"coverage\\\":2},\\\"fast_pass_rate\\\":{\\\"median\\\":0.5,\\\"iqr\\\":[0,1],\\\"coverage\\\":2}},\\\"by_relation\\\":{\\\"self\\\":{\\\"n\\\":1,\\\"trust_median\\\":7,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]},\\\"colleague\\\":{\\\"n\\\":1,\\\"trust_median\\\":18,\\\"flags_top\\\":[]}},\\\"for_current_target\\\":null}}\"'),
(13,43,56,'2025-09-03 15:05:52','{\"trust_score\":8,\"trust_index\":40,\"flags\":[\"too_fast\",\"one_click_fast_read\"],\"rationale\":\"Current submission shows strong low-effort signals: one_click_rate=1.0 with one_click_all=true indicating no answer changes; fast_pass_rate=1.0 indicating too_fast; reading_speed_median_100ch=1058ms and short_read_rate=0.333 do not trigger fast_read, so no fast_read flag. Content stats show moderate dominant_share=0.33 and extremes_share=0.33, no extremes_only or too_uniform. Zigzag_index=0.20 and pace_cv=0.45 indicate moderate variability, no suspicious_pattern. Baseline is unavailable, so no leniency/severity bias adjustment. History shows this rater typically exhibits too_fast and one_click_fast_read flags with median trust ~10 and an upward trend; current behavior aligns with history. Thus, apply -3 for strong low-effort (too_fast and one_click_fast_read), no positive evidence flags. A\",\"relation_type\":\"colleague\",\"target_id\":56,\"ai_timestamp\":\"2025-09-03T15:05:52+02:00\",\"features_snapshot\":{\"avg_ms_per_item\":947,\"uniform_ratio\":0.333000000000000018207657603852567262947559356689453125,\"entropy\":null,\"zigzag_index\":0.200000000000000011102230246251565404236316680908203125,\"fast_pass_rate\":1,\"device_type\":\"desktop\",\"pace_cv\":0.449000000000000010214051826551440171897411346435546875,\"pace_median_ms\":622,\"pace_iqr_ms\":[466,1097],\"one_click_rate\":1,\"active_ratio\":1,\"short_read_rate\":0.333000000000000018207657603852567262947559356689453125,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":1058,\"fast_read_rate_100ch\":0.0909999999999999975575093458246556110680103302001953125},\"baseline_echo\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"7596c068-396e-42b5-aa41-e2fdd8e88f0f\\\",\\\"started_at\\\":\\\"2025-09-03T13:05:39.362Z\\\",\\\"finished_at\\\":\\\"2025-09-03T13:05:50.729Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":1278,\\\"viewport_h\\\":944},\\\"items_count\\\":12,\\\"display_order\\\":[50,32,34,41,23,25,22,24,44,40,45,39],\\\"total_ms\\\":11366,\\\"visible_ms\\\":11366,\\\"active_ms\\\":11366,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":12,\\\"keydowns\\\":0,\\\"scrolls\\\":81,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":12},\\\"items\\\":[{\\\"question_id\\\":50,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":60,\\\"view_read_ms\\\":1750,\\\"view_read_ms_per_100ch\\\":2917,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":14,\\\"first_interaction_ms\\\":1764,\\\"value_path\\\":[{\\\"ms\\\":1764,\\\"v\\\":3}],\\\"last_value\\\":3,\\\"changes_count\\\":0,\\\"focus_ms\\\":367,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":32,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":102,\\\"view_read_ms\\\":2285,\\\"view_read_ms_per_100ch\\\":2240,\\\"seq_read_ms_raw\\\":535,\\\"seq_read_ms_active\\\":535,\\\"seq_read_ms_per_100ch\\\":525,\\\"first_seen_ms\\\":14,\\\"first_interaction_ms\\\":2299,\\\"value_path\\\":[{\\\"ms\\\":2299,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":893,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":34,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":71,\\\"view_read_ms\\\":2821,\\\"view_read_ms_per_100ch\\\":3973,\\\"seq_read_ms_raw\\\":536,\\\"seq_read_ms_active\\\":536,\\\"seq_read_ms_per_100ch\\\":755,\\\"first_seen_ms\\\":14,\\\"first_interaction_ms\\\":2835,\\\"value_path\\\":[{\\\"ms\\\":2835,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1150,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":41,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":39,\\\"view_read_ms\\\":3287,\\\"view_read_ms_per_100ch\\\":8428,\\\"seq_read_ms_raw\\\":466,\\\"seq_read_ms_active\\\":466,\\\"seq_read_ms_per_100ch\\\":1195,\\\"first_seen_ms\\\":14,\\\"first_interaction_ms\\\":3301,\\\"value_path\\\":[{\\\"ms\\\":3301,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":1055,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":23,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":68,\\\"view_read_ms\\\":801,\\\"view_read_ms_per_100ch\\\":1178,\\\"seq_read_ms_raw\\\":1097,\\\"seq_read_ms_active\\\":1097,\\\"seq_read_ms_per_100ch\\\":1613,\\\"first_seen_ms\\\":3597,\\\"first_interaction_ms\\\":4398,\\\"value_path\\\":[{\\\"ms\\\":4398,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":693,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":25,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":81,\\\"view_read_ms\\\":2141,\\\"view_read_ms_per_100ch\\\":2643,\\\"seq_read_ms_raw\\\":1381,\\\"seq_read_ms_active\\\":1381,\\\"seq_read_ms_per_100ch\\\":1705,\\\"first_seen_ms\\\":3638,\\\"first_interaction_ms\\\":5779,\\\"value_path\\\":[{\\\"ms\\\":5779,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":1258,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":22,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":116,\\\"view_read_ms\\\":1230,\\\"view_read_ms_per_100ch\\\":1060,\\\"seq_read_ms_raw\\\":528,\\\"seq_read_ms_active\\\":528,\\\"seq_read_ms_per_100ch\\\":455,\\\"first_seen_ms\\\":5077,\\\"first_interaction_ms\\\":6307,\\\"value_path\\\":[{\\\"ms\\\":6307,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":821,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":24,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":633,\\\"view_read_ms_per_100ch\\\":763,\\\"seq_read_ms_raw\\\":878,\\\"seq_read_ms_active\\\":878,\\\"seq_read_ms_per_100ch\\\":1058,\\\"first_seen_ms\\\":6552,\\\"first_interaction_ms\\\":7185,\\\"value_path\\\":[{\\\"ms\\\":7185,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":378,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":44,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":88,\\\"view_read_ms\\\":1822,\\\"view_read_ms_per_100ch\\\":2070,\\\"seq_read_ms_raw\\\":1274,\\\"seq_read_ms_active\\\":1274,\\\"seq_read_ms_per_100ch\\\":1448,\\\"first_seen_ms\\\":6637,\\\"first_interaction_ms\\\":8459,\\\"value_path\\\":[{\\\"ms\\\":8459,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":434,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":40,\\\"index\\\":10,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":104,\\\"view_read_ms\\\":1234,\\\"view_read_ms_per_100ch\\\":1187,\\\"seq_read_ms_raw\\\":278,\\\"seq_read_ms_active\\\":278,\\\"seq_read_ms_per_100ch\\\":267,\\\"first_seen_ms\\\":7503,\\\"first_interaction_ms\\\":8737,\\\"value_path\\\":[{\\\"ms\\\":8737,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":559,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":45,\\\"index\\\":11,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":143,\\\"view_read_ms\\\":1802,\\\"view_read_ms_per_100ch\\\":1260,\\\"seq_read_ms_raw\\\":622,\\\"seq_read_ms_active\\\":622,\\\"seq_read_ms_per_100ch\\\":435,\\\"first_seen_ms\\\":7557,\\\"first_interaction_ms\\\":9359,\\\"value_path\\\":[{\\\"ms\\\":9359,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":995,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":39,\\\"index\\\":12,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":43,\\\"view_read_ms\\\":2486,\\\"view_read_ms_per_100ch\\\":5781,\\\"seq_read_ms_raw\\\":738,\\\"seq_read_ms_active\\\":738,\\\"seq_read_ms_per_100ch\\\":1716,\\\"first_seen_ms\\\":7611,\\\"first_interaction_ms\\\":10097,\\\"value_path\\\":[{\\\"ms\\\":10097,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":418,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":13,\\\"user_id\\\":43,\\\"target_id\\\":56,\\\"relation_type\\\":\\\"colleague\\\",\\\"answers_count\\\":12,\\\"items_count_server\\\":12,\\\"items_count_client\\\":12,\\\"server_received_at\\\":\\\"2025-09-03T15:05:52+02:00\\\",\\\"client_started_at\\\":\\\"2025-09-03T13:05:39.362Z\\\",\\\"client_finished_at\\\":\\\"2025-09-03T13:05:50.729Z\\\",\\\"measurement_uuid\\\":\\\"7596c068-396e-42b5-aa41-e2fdd8e88f0f\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":12,\\\"value_counts\\\":{\\\"6\\\":4,\\\"7\\\":4,\\\"5\\\":2,\\\"3\\\":1,\\\"4\\\":1},\\\"dominant_value\\\":6,\\\"dominant_share\\\":0.333000000000000018207657603852567262947559356689453125,\\\"extremes_share\\\":0.333000000000000018207657603852567262947559356689453125,\\\"all_same_value\\\":false,\\\"mean_percent\\\":82.1400000000000005684341886080801486968994140625,\\\"stddev_percent\\\":17.620000000000000994759830064140260219573974609375},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":1,\\\"fast_clicks_p1000\\\":0.72699999999999997957189634689711965620517730712890625,\\\"pace_cv\\\":0.448566706572478768322298492421396076679229736328125,\\\"pace_median_ms\\\":622,\\\"pace_iqr_ms\\\":[466,1097],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.333000000000000018207657603852567262947559356689453125,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":1,\\\"assessment_span\\\":\\\"previous_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":78.6700000000000017053025658242404460906982421875,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":3,\\\"window_days\\\":3,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"relation_mix\\\":{\\\"colleague\\\":2,\\\"self\\\":1},\\\"device_mix\\\":{\\\"desktop\\\":3},\\\"trust_summary\\\":{\\\"median\\\":10,\\\"iqr\\\":[7,18],\\\"low_rate\\\":0.330000000000000015543122344752191565930843353271484375,\\\"high_rate\\\":0.330000000000000015543122344752191565930843353271484375,\\\"trend\\\":\\\"up\\\"},\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":0.67000000000000003996802888650563545525074005126953125},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":0.67000000000000003996802888650563545525074005126953125}],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":916,\\\"iqr\\\":[880,1576],\\\"coverage\\\":3},\\\"uniform_ratio\\\":{\\\"median\\\":0.467000000000000026201263381153694353997707366943359375,\\\"iqr\\\":[0.40000000000000002220446049250313080847263336181640625,0.5],\\\"coverage\\\":3},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.5,\\\"iqr\\\":[0.462000000000000021760371282653068192303180694580078125,0.5380000000000000337507799486047588288784027099609375],\\\"coverage\\\":3},\\\"fast_pass_rate\\\":{\\\"median\\\":1,\\\"iqr\\\":[0,1],\\\"coverage\\\":3}},\\\"by_relation\\\":{\\\"colleague\\\":{\\\"n\\\":2,\\\"trust_median\\\":10,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":0.5},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":0.5}]},\\\"self\\\":{\\\"n\\\":1,\\\"trust_median\\\":7,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]}},\\\"for_current_target\\\":null}}\"'),
(13,57,43,'2025-09-03 15:07:23','{\"trust_score\":8,\"trust_index\":40,\"flags\":[\"too_fast\",\"one_click_fast_read\",\"suspicious_pattern\"],\"rationale\":\"Current submission shows one_click_rate=1.00 and fast_pass_rate=0.833 >0.70 indicating speeding (flag too_fast). Zigzag_index=0.833 >0.60 with pace_cv=0.615 >0.50 indicates suspicious_pattern (inconsistent or random responding). Reading_speed_median_100ch=1100 >400 and short_read_rate=0.375 <0.60 so no fast_read flagged but one_click_fast_read is flagged since one_click_rate=1.00 and one_click_all=true combined with too_fast. Content_stats dominant_share=0.375 and uniform_ratio=0.375 <0.60, and extremes_share=0 so no extremes_only or too_uniform. Baseline not available so no delta_mean adjustment. History shows previous trend up with median trust 9 but flags too_fast and one_click_fast_read present previously, consistent with current behavior. Balanced guidance leads to moderate penalties:\",\"relation_type\":\"ceo\",\"target_id\":43,\"ai_timestamp\":\"2025-09-03T15:10:00+02:00\",\"features_snapshot\":{\"avg_ms_per_item\":914,\"uniform_ratio\":0.375,\"entropy\":null,\"zigzag_index\":0.8329999999999999626965063725947402417659759521484375,\"fast_pass_rate\":0.8329999999999999626965063725947402417659759521484375,\"device_type\":\"desktop\",\"pace_cv\":0.6149999999999999911182158029987476766109466552734375,\"pace_median_ms\":796,\"pace_iqr_ms\":[412,1226],\"one_click_rate\":1,\"active_ratio\":1,\"short_read_rate\":0.375,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":1100,\"fast_read_rate_100ch\":0},\"baseline_echo\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"8aa07fd7-bc0f-4282-8391-4880cffa0225\\\",\\\"started_at\\\":\\\"2025-09-03T13:07:14.986Z\\\",\\\"finished_at\\\":\\\"2025-09-03T13:07:22.296Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":1278,\\\"viewport_h\\\":944},\\\"items_count\\\":8,\\\"display_order\\\":[48,49,15,37,35,47,38,36],\\\"total_ms\\\":7310,\\\"visible_ms\\\":7310,\\\"active_ms\\\":7310,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":8,\\\"keydowns\\\":0,\\\"scrolls\\\":50,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":8},\\\"items\\\":[{\\\"question_id\\\":48,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":85,\\\"view_read_ms\\\":797,\\\"view_read_ms_per_100ch\\\":938,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":11,\\\"first_interaction_ms\\\":808,\\\"value_path\\\":[{\\\"ms\\\":808,\\\"v\\\":3}],\\\"last_value\\\":3,\\\"changes_count\\\":0,\\\"focus_ms\\\":951,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":49,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":43,\\\"view_read_ms\\\":1270,\\\"view_read_ms_per_100ch\\\":2953,\\\"seq_read_ms_raw\\\":473,\\\"seq_read_ms_active\\\":473,\\\"seq_read_ms_per_100ch\\\":1100,\\\"first_seen_ms\\\":11,\\\"first_interaction_ms\\\":1281,\\\"value_path\\\":[{\\\"ms\\\":1281,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":467,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":15,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":98,\\\"view_read_ms\\\":1976,\\\"view_read_ms_per_100ch\\\":2016,\\\"seq_read_ms_raw\\\":706,\\\"seq_read_ms_active\\\":706,\\\"seq_read_ms_per_100ch\\\":720,\\\"first_seen_ms\\\":11,\\\"first_interaction_ms\\\":1987,\\\"value_path\\\":[{\\\"ms\\\":1987,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":867,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":37,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":71,\\\"view_read_ms\\\":1894,\\\"view_read_ms_per_100ch\\\":2668,\\\"seq_read_ms_raw\\\":528,\\\"seq_read_ms_active\\\":528,\\\"seq_read_ms_per_100ch\\\":744,\\\"first_seen_ms\\\":2283,\\\"first_interaction_ms\\\":4177,\\\"value_path\\\":[{\\\"ms\\\":4177,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":627,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":35,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":66,\\\"view_read_ms\\\":1296,\\\"view_read_ms_per_100ch\\\":1964,\\\"seq_read_ms_raw\\\":1662,\\\"seq_read_ms_active\\\":1662,\\\"seq_read_ms_per_100ch\\\":2518,\\\"first_seen_ms\\\":2353,\\\"first_interaction_ms\\\":3649,\\\"value_path\\\":[{\\\"ms\\\":3649,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":1329,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":47,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":48,\\\"view_read_ms\\\":2468,\\\"view_read_ms_per_100ch\\\":5142,\\\"seq_read_ms_raw\\\":698,\\\"seq_read_ms_active\\\":698,\\\"seq_read_ms_per_100ch\\\":1454,\\\"first_seen_ms\\\":2407,\\\"first_interaction_ms\\\":4875,\\\"value_path\\\":[{\\\"ms\\\":4875,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":574,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":38,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":66,\\\"view_read_ms\\\":2954,\\\"view_read_ms_per_100ch\\\":4476,\\\"seq_read_ms_raw\\\":886,\\\"seq_read_ms_active\\\":886,\\\"seq_read_ms_per_100ch\\\":1342,\\\"first_seen_ms\\\":2807,\\\"first_interaction_ms\\\":5761,\\\"value_path\\\":[{\\\"ms\\\":5761,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":525,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":36,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":73,\\\"view_read_ms\\\":3300,\\\"view_read_ms_per_100ch\\\":4521,\\\"seq_read_ms_raw\\\":412,\\\"seq_read_ms_active\\\":412,\\\"seq_read_ms_per_100ch\\\":564,\\\"first_seen_ms\\\":2873,\\\"first_interaction_ms\\\":6173,\\\"value_path\\\":[{\\\"ms\\\":6173,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":700,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":13,\\\"user_id\\\":57,\\\"target_id\\\":43,\\\"relation_type\\\":\\\"ceo\\\",\\\"answers_count\\\":8,\\\"items_count_server\\\":8,\\\"items_count_client\\\":8,\\\"server_received_at\\\":\\\"2025-09-03T15:07:23+02:00\\\",\\\"client_started_at\\\":\\\"2025-09-03T13:07:14.986Z\\\",\\\"client_finished_at\\\":\\\"2025-09-03T13:07:22.296Z\\\",\\\"measurement_uuid\\\":\\\"8aa07fd7-bc0f-4282-8391-4880cffa0225\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":8,\\\"value_counts\\\":{\\\"5\\\":3,\\\"4\\\":2,\\\"6\\\":2,\\\"3\\\":1},\\\"dominant_value\\\":5,\\\"dominant_share\\\":0.375,\\\"extremes_share\\\":0,\\\"all_same_value\\\":false,\\\"mean_percent\\\":67.8599999999999994315658113919198513031005859375,\\\"stddev_percent\\\":13.8300000000000000710542735760100185871124267578125},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":true,\\\"fast_clicks_p1500\\\":0.8329999999999999626965063725947402417659759521484375,\\\"fast_clicks_p1000\\\":0.6670000000000000373034936274052597582340240478515625,\\\"pace_cv\\\":0.61486531522886334055755241934093646705150604248046875,\\\"pace_median_ms\\\":796,\\\"pace_iqr_ms\\\":[412,1226],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.375,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":1,\\\"assessment_span\\\":\\\"current_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":70.5,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":5,\\\"window_days\\\":3,\\\"tier\\\":\\\"medium\\\",\\\"guidance\\\":\\\"balanced\\\",\\\"relation_mix\\\":{\\\"ceo\\\":3,\\\"self\\\":2},\\\"device_mix\\\":{\\\"desktop\\\":5},\\\"trust_summary\\\":{\\\"median\\\":9,\\\"iqr\\\":[9,10],\\\"low_rate\\\":0,\\\"high_rate\\\":0,\\\"trend\\\":\\\"up\\\"},\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"fast_read\\\",\\\"rate\\\":0.40000000000000002220446049250313080847263336181640625}],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":987,\\\"iqr\\\":[792,989],\\\"coverage\\\":5},\\\"uniform_ratio\\\":{\\\"median\\\":0.467000000000000026201263381153694353997707366943359375,\\\"iqr\\\":[0.2220000000000000028865798640254070051014423370361328125,0.55600000000000004973799150320701301097869873046875],\\\"coverage\\\":5},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.2310000000000000108801856413265340961515903472900390625,\\\"iqr\\\":[0,0.299999999999999988897769753748434595763683319091796875],\\\"coverage\\\":5},\\\"fast_pass_rate\\\":{\\\"median\\\":1,\\\"iqr\\\":[0.90000000000000002220446049250313080847263336181640625,1],\\\"coverage\\\":5}},\\\"by_relation\\\":{\\\"ceo\\\":{\\\"n\\\":3,\\\"trust_median\\\":9,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]},\\\"self\\\":{\\\"n\\\":2,\\\"trust_median\\\":9,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]}},\\\"for_current_target\\\":null}}\"'),
(13,57,55,'2025-09-03 15:07:03','{\"trust_score\":9,\"trust_index\":45,\"flags\":[\"too_fast\",\"one_click_fast_read\"],\"rationale\":\"Current submission flags too_fast due to fast_pass_rate=0.929 and one_click_fast_read due to one_click_rate=0.933 combined with fast_pass_rate and pacing (pace_cv=0.585) indicating speeding and low-effort. Reading_speed_median_100ch=804.5 and short_read_rate=0.467 do not qualify for fast_read. Content_stats show moderate dominant_share=0.467 and low extremes_share=0.067, indicating no extremes_only or too_uniform pattern. Zigzag_index=0.231 and pace_cv=0.585 suggest moderate variability, but one-click rate indicates a high shortcut usage. Baseline unavailable, so no penalty or bonus from delta_mean. History shows cold_start tier with prior flags for too_fast and one_click_fast_read, supporting consistent low-effort pattern. Guidance is be_kind, so severity penalization is moderate (-3 from\",\"relation_type\":\"ceo\",\"target_id\":55,\"ai_timestamp\":\"2025-09-03T15:07:03+02:00\",\"features_snapshot\":{\"avg_ms_per_item\":792,\"uniform_ratio\":0.467000000000000026201263381153694353997707366943359375,\"entropy\":null,\"zigzag_index\":0.2310000000000000108801856413265340961515903472900390625,\"fast_pass_rate\":0.9290000000000000479616346638067625463008880615234375,\"device_type\":\"desktop\",\"pace_cv\":0.58499999999999996447286321199499070644378662109375,\"pace_median_ms\":560,\"pace_iqr_ms\":[326,792],\"one_click_rate\":0.9330000000000000515143483426072634756565093994140625,\"active_ratio\":1,\"short_read_rate\":0.467000000000000026201263381153694353997707366943359375,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":804.5,\"fast_read_rate_100ch\":0.07099999999999999367172875963660771958529949188232421875},\"baseline_echo\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"8935b7c0-d7ba-48a6-972c-341b6115bdfe\\\",\\\"started_at\\\":\\\"2025-09-03T13:06:49.941Z\\\",\\\"finished_at\\\":\\\"2025-09-03T13:07:01.815Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":1278,\\\"viewport_h\\\":944},\\\"items_count\\\":15,\\\"display_order\\\":[1,26,29,28,5,6,13,7,4,3,8,46,9,10,11],\\\"total_ms\\\":11873,\\\"visible_ms\\\":11873,\\\"active_ms\\\":11873,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":16,\\\"keydowns\\\":0,\\\"scrolls\\\":101,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":15},\\\"items\\\":[{\\\"question_id\\\":1,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":834,\\\"view_read_ms_per_100ch\\\":1005,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":26,\\\"first_interaction_ms\\\":860,\\\"value_path\\\":[{\\\"ms\\\":860,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":341,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":26,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":88,\\\"view_read_ms\\\":1532,\\\"view_read_ms_per_100ch\\\":1741,\\\"seq_read_ms_raw\\\":698,\\\"seq_read_ms_active\\\":698,\\\"seq_read_ms_per_100ch\\\":793,\\\"first_seen_ms\\\":26,\\\"first_interaction_ms\\\":1558,\\\"value_path\\\":[{\\\"ms\\\":1558,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":559,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":29,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":47,\\\"view_read_ms\\\":1850,\\\"view_read_ms_per_100ch\\\":3936,\\\"seq_read_ms_raw\\\":318,\\\"seq_read_ms_active\\\":318,\\\"seq_read_ms_per_100ch\\\":677,\\\"first_seen_ms\\\":26,\\\"first_interaction_ms\\\":1876,\\\"value_path\\\":[{\\\"ms\\\":1876,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1142,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":28,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":97,\\\"view_read_ms\\\":2642,\\\"view_read_ms_per_100ch\\\":2724,\\\"seq_read_ms_raw\\\":792,\\\"seq_read_ms_active\\\":792,\\\"seq_read_ms_per_100ch\\\":816,\\\"first_seen_ms\\\":26,\\\"first_interaction_ms\\\":2668,\\\"value_path\\\":[{\\\"ms\\\":2668,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":364,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":5,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":65,\\\"view_read_ms\\\":1474,\\\"view_read_ms_per_100ch\\\":2268,\\\"seq_read_ms_raw\\\":1002,\\\"seq_read_ms_active\\\":1002,\\\"seq_read_ms_per_100ch\\\":1542,\\\"first_seen_ms\\\":2196,\\\"first_interaction_ms\\\":3670,\\\"value_path\\\":[{\\\"ms\\\":3670,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":348,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":6,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":43,\\\"view_read_ms\\\":1346,\\\"view_read_ms_per_100ch\\\":3130,\\\"seq_read_ms_raw\\\":646,\\\"seq_read_ms_active\\\":646,\\\"seq_read_ms_per_100ch\\\":1502,\\\"first_seen_ms\\\":2970,\\\"first_interaction_ms\\\":4316,\\\"value_path\\\":[{\\\"ms\\\":4316,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":558,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":13,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":67,\\\"view_read_ms\\\":1625,\\\"view_read_ms_per_100ch\\\":2425,\\\"seq_read_ms_raw\\\":332,\\\"seq_read_ms_active\\\":332,\\\"seq_read_ms_per_100ch\\\":496,\\\"first_seen_ms\\\":3023,\\\"first_interaction_ms\\\":4648,\\\"value_path\\\":[{\\\"ms\\\":4648,\\\"v\\\":6},{\\\"ms\\\":4998,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":1,\\\"focus_ms\\\":1320,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":7,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":76,\\\"view_read_ms\\\":2941,\\\"view_read_ms_per_100ch\\\":3870,\\\"seq_read_ms_raw\\\":1369,\\\"seq_read_ms_active\\\":1369,\\\"seq_read_ms_per_100ch\\\":1801,\\\"first_seen_ms\\\":3076,\\\"first_interaction_ms\\\":6017,\\\"value_path\\\":[{\\\"ms\\\":6017,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":253,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":4,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":60,\\\"view_read_ms\\\":1183,\\\"view_read_ms_per_100ch\\\":1972,\\\"seq_read_ms_raw\\\":441,\\\"seq_read_ms_active\\\":441,\\\"seq_read_ms_per_100ch\\\":735,\\\"first_seen_ms\\\":5275,\\\"first_interaction_ms\\\":6458,\\\"value_path\\\":[{\\\"ms\\\":6458,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":641,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":3,\\\"index\\\":10,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":88,\\\"view_read_ms\\\":1468,\\\"view_read_ms_per_100ch\\\":1668,\\\"seq_read_ms_raw\\\":326,\\\"seq_read_ms_active\\\":326,\\\"seq_read_ms_per_100ch\\\":370,\\\"first_seen_ms\\\":5316,\\\"first_interaction_ms\\\":6784,\\\"value_path\\\":[{\\\"ms\\\":6784,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":787,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":8,\\\"index\\\":11,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":56,\\\"view_read_ms\\\":1275,\\\"view_read_ms_per_100ch\\\":2277,\\\"seq_read_ms_raw\\\":1608,\\\"seq_read_ms_active\\\":1608,\\\"seq_read_ms_per_100ch\\\":2871,\\\"first_seen_ms\\\":7117,\\\"first_interaction_ms\\\":8392,\\\"value_path\\\":[{\\\"ms\\\":8392,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":805,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":46,\\\"index\\\":12,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":36,\\\"view_read_ms\\\":1527,\\\"view_read_ms_per_100ch\\\":4242,\\\"seq_read_ms_raw\\\":318,\\\"seq_read_ms_active\\\":318,\\\"seq_read_ms_per_100ch\\\":883,\\\"first_seen_ms\\\":7183,\\\"first_interaction_ms\\\":8710,\\\"value_path\\\":[{\\\"ms\\\":8710,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":506,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":9,\\\"index\\\":13,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":1467,\\\"view_read_ms_per_100ch\\\":1767,\\\"seq_read_ms_raw\\\":474,\\\"seq_read_ms_active\\\":474,\\\"seq_read_ms_per_100ch\\\":571,\\\"first_seen_ms\\\":7717,\\\"first_interaction_ms\\\":9184,\\\"value_path\\\":[{\\\"ms\\\":9184,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":546,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":10,\\\"index\\\":14,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":81,\\\"view_read_ms\\\":1791,\\\"view_read_ms_per_100ch\\\":2211,\\\"seq_read_ms_raw\\\":364,\\\"seq_read_ms_active\\\":364,\\\"seq_read_ms_per_100ch\\\":449,\\\"first_seen_ms\\\":7757,\\\"first_interaction_ms\\\":9548,\\\"value_path\\\":[{\\\"ms\\\":9548,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":542,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":11,\\\"index\\\":15,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":79,\\\"view_read_ms\\\":2473,\\\"view_read_ms_per_100ch\\\":3130,\\\"seq_read_ms_raw\\\":708,\\\"seq_read_ms_active\\\":708,\\\"seq_read_ms_per_100ch\\\":896,\\\"first_seen_ms\\\":7783,\\\"first_interaction_ms\\\":10256,\\\"value_path\\\":[{\\\"ms\\\":10256,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":897,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":13,\\\"user_id\\\":57,\\\"target_id\\\":55,\\\"relation_type\\\":\\\"ceo\\\",\\\"answers_count\\\":15,\\\"items_count_server\\\":15,\\\"items_count_client\\\":15,\\\"server_received_at\\\":\\\"2025-09-03T15:07:03+02:00\\\",\\\"client_started_at\\\":\\\"2025-09-03T13:06:49.941Z\\\",\\\"client_finished_at\\\":\\\"2025-09-03T13:07:01.815Z\\\",\\\"measurement_uuid\\\":\\\"8935b7c0-d7ba-48a6-972c-341b6115bdfe\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":15,\\\"value_counts\\\":{\\\"5\\\":7,\\\"4\\\":4,\\\"6\\\":3,\\\"7\\\":1},\\\"dominant_value\\\":5,\\\"dominant_share\\\":0.467000000000000026201263381153694353997707366943359375,\\\"extremes_share\\\":0.067000000000000003996802888650563545525074005126953125,\\\"all_same_value\\\":false,\\\"mean_percent\\\":72.3799999999999954525264911353588104248046875,\\\"stddev_percent\\\":12.199999999999999289457264239899814128875732421875},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":true,\\\"fast_clicks_p1500\\\":0.9290000000000000479616346638067625463008880615234375,\\\"fast_clicks_p1000\\\":0.786000000000000031974423109204508364200592041015625,\\\"pace_cv\\\":0.5854239288115696826508838057634420692920684814453125,\\\"pace_median_ms\\\":560,\\\"pace_iqr_ms\\\":[326,792],\\\"one_click_rate\\\":0.9330000000000000515143483426072634756565093994140625,\\\"one_click_all\\\":false,\\\"short_read_p1500\\\":0.467000000000000026201263381153694353997707366943359375,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":1,\\\"assessment_span\\\":\\\"current_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":77.6700000000000017053025658242404460906982421875,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":4,\\\"window_days\\\":3,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"relation_mix\\\":{\\\"self\\\":2,\\\"ceo\\\":2},\\\"device_mix\\\":{\\\"desktop\\\":4},\\\"trust_summary\\\":{\\\"median\\\":9.5,\\\"iqr\\\":[9,12],\\\"low_rate\\\":0,\\\"high_rate\\\":0,\\\"trend\\\":\\\"up\\\"},\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"fast_read\\\",\\\"rate\\\":0.5}],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":988,\\\"iqr\\\":[961,1078],\\\"coverage\\\":4},\\\"uniform_ratio\\\":{\\\"median\\\":0.48650000000000004352074256530613638460636138916015625,\\\"iqr\\\":[0.2220000000000000028865798640254070051014423370361328125,0.6670000000000000373034936274052597582340240478515625],\\\"coverage\\\":4},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.221499999999999974686915038546430878341197967529296875,\\\"iqr\\\":[0,0.5],\\\"coverage\\\":4},\\\"fast_pass_rate\\\":{\\\"median\\\":1,\\\"iqr\\\":[0.90000000000000002220446049250313080847263336181640625,1],\\\"coverage\\\":4}},\\\"by_relation\\\":{\\\"self\\\":{\\\"n\\\":2,\\\"trust_median\\\":9,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]},\\\"ceo\\\":{\\\"n\\\":2,\\\"trust_median\\\":9,\\\"flags_top\\\":[{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1}]}},\\\"for_current_target\\\":null}}\"'),
(13,57,56,'2025-09-03 15:08:19','{\"trust_score\":7,\"trust_index\":35,\"flags\":[\"too_fast\",\"one_click_fast_read\"],\"rationale\":\"The submission shows clear low-effort response behavior: one_click_rate=1.0 with one_click_all=true indicates all items answered with one click, and fast_pass_rate=1.0 shows extremely rapid answering, triggering the flags too_fast and one_click_fast_read. The reading_speed_median_100ch=934 ms and short_read_rate=0.33 do not indicate fast_read, so fast_read flag is not added. Content_stats show no extremes_only or too_uniform pattern (dominant_share=0.33 <0.60 and extremes_share=0.083 <0.80), and zigzag_index=0.30 indicates some variation. The pace_cv=0.33 shows moderate pace variability, but lack of changes (changes_count=0 per item) and suspicious_one_click=true confirm a strong response-set pattern. Baseline is unavailable, so no baseline adjustments apply. History for ceo relation shows\",\"relation_type\":\"ceo\",\"target_id\":56,\"ai_timestamp\":\"2025-09-03T15:08:20Z\",\"features_snapshot\":{\"avg_ms_per_item\":888,\"uniform_ratio\":0.333000000000000018207657603852567262947559356689453125,\"entropy\":null,\"zigzag_index\":0.299999999999999988897769753748434595763683319091796875,\"fast_pass_rate\":1,\"device_type\":\"desktop\",\"pace_cv\":0.328000000000000013766765505351941101253032684326171875,\"pace_median_ms\":822,\"pace_iqr_ms\":[420,924],\"one_click_rate\":1,\"active_ratio\":1,\"short_read_rate\":0.333000000000000018207657603852567262947559356689453125,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":934,\"fast_read_rate_100ch\":0},\"baseline_echo\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"68c24406-5822-450f-8714-5f0f509e41ce\\\",\\\"started_at\\\":\\\"2025-09-03T13:08:07.119Z\\\",\\\"finished_at\\\":\\\"2025-09-03T13:08:17.780Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":1278,\\\"viewport_h\\\":944},\\\"items_count\\\":12,\\\"display_order\\\":[50,32,41,34,22,25,23,24,40,44,45,39],\\\"total_ms\\\":10661,\\\"visible_ms\\\":10661,\\\"active_ms\\\":10661,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":12,\\\"keydowns\\\":0,\\\"scrolls\\\":100,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":12},\\\"items\\\":[{\\\"question_id\\\":50,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":60,\\\"view_read_ms\\\":1057,\\\"view_read_ms_per_100ch\\\":1762,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":28,\\\"first_interaction_ms\\\":1085,\\\"value_path\\\":[{\\\"ms\\\":1085,\\\"v\\\":3}],\\\"last_value\\\":3,\\\"changes_count\\\":0,\\\"focus_ms\\\":453,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":32,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":102,\\\"view_read_ms\\\":1794,\\\"view_read_ms_per_100ch\\\":1759,\\\"seq_read_ms_raw\\\":737,\\\"seq_read_ms_active\\\":737,\\\"seq_read_ms_per_100ch\\\":723,\\\"first_seen_ms\\\":28,\\\"first_interaction_ms\\\":1822,\\\"value_path\\\":[{\\\"ms\\\":1822,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":1442,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":41,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":39,\\\"view_read_ms\\\":2694,\\\"view_read_ms_per_100ch\\\":6908,\\\"seq_read_ms_raw\\\":900,\\\"seq_read_ms_active\\\":900,\\\"seq_read_ms_per_100ch\\\":2308,\\\"first_seen_ms\\\":28,\\\"first_interaction_ms\\\":2722,\\\"value_path\\\":[{\\\"ms\\\":2722,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":1113,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":34,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":71,\\\"view_read_ms\\\":3812,\\\"view_read_ms_per_100ch\\\":5369,\\\"seq_read_ms_raw\\\":1118,\\\"seq_read_ms_active\\\":1118,\\\"seq_read_ms_per_100ch\\\":1575,\\\"first_seen_ms\\\":28,\\\"first_interaction_ms\\\":3840,\\\"value_path\\\":[{\\\"ms\\\":3840,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":454,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":22,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":116,\\\"view_read_ms\\\":1846,\\\"view_read_ms_per_100ch\\\":1591,\\\"seq_read_ms_raw\\\":1150,\\\"seq_read_ms_active\\\":1150,\\\"seq_read_ms_per_100ch\\\":991,\\\"first_seen_ms\\\":3144,\\\"first_interaction_ms\\\":4990,\\\"value_path\\\":[{\\\"ms\\\":4990,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":767,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":25,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":81,\\\"view_read_ms\\\":2213,\\\"view_read_ms_per_100ch\\\":2732,\\\"seq_read_ms_raw\\\":420,\\\"seq_read_ms_active\\\":420,\\\"seq_read_ms_per_100ch\\\":519,\\\"first_seen_ms\\\":3197,\\\"first_interaction_ms\\\":5410,\\\"value_path\\\":[{\\\"ms\\\":5410,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":827,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":23,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":68,\\\"view_read_ms\\\":2515,\\\"view_read_ms_per_100ch\\\":3699,\\\"seq_read_ms_raw\\\":364,\\\"seq_read_ms_active\\\":364,\\\"seq_read_ms_per_100ch\\\":535,\\\"first_seen_ms\\\":3259,\\\"first_interaction_ms\\\":5774,\\\"value_path\\\":[{\\\"ms\\\":5774,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":514,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":24,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":2406,\\\"view_read_ms_per_100ch\\\":2899,\\\"seq_read_ms_raw\\\":924,\\\"seq_read_ms_active\\\":924,\\\"seq_read_ms_per_100ch\\\":1113,\\\"first_seen_ms\\\":4292,\\\"first_interaction_ms\\\":6698,\\\"value_path\\\":[{\\\"ms\\\":6698,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":525,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":40,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":104,\\\"view_read_ms\\\":1163,\\\"view_read_ms_per_100ch\\\":1118,\\\"seq_read_ms_raw\\\":622,\\\"seq_read_ms_active\\\":622,\\\"seq_read_ms_per_100ch\\\":598,\\\"first_seen_ms\\\":6157,\\\"first_interaction_ms\\\":7320,\\\"value_path\\\":[{\\\"ms\\\":7320,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":973,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":44,\\\"index\\\":10,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":88,\\\"view_read_ms\\\":1931,\\\"view_read_ms_per_100ch\\\":2194,\\\"seq_read_ms_raw\\\":822,\\\"seq_read_ms_active\\\":822,\\\"seq_read_ms_per_100ch\\\":934,\\\"first_seen_ms\\\":6211,\\\"first_interaction_ms\\\":8142,\\\"value_path\\\":[{\\\"ms\\\":8142,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":582,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":45,\\\"index\\\":11,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":143,\\\"view_read_ms\\\":1421,\\\"view_read_ms_per_100ch\\\":994,\\\"seq_read_ms_raw\\\":862,\\\"seq_read_ms_active\\\":862,\\\"seq_read_ms_per_100ch\\\":603,\\\"first_seen_ms\\\":7583,\\\"first_interaction_ms\\\":9004,\\\"value_path\\\":[{\\\"ms\\\":9004,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":459,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":39,\\\"index\\\":12,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":43,\\\"view_read_ms\\\":1106,\\\"view_read_ms_per_100ch\\\":2572,\\\"seq_read_ms_raw\\\":512,\\\"seq_read_ms_active\\\":512,\\\"seq_read_ms_per_100ch\\\":1191,\\\"first_seen_ms\\\":8410,\\\"first_interaction_ms\\\":9516,\\\"value_path\\\":[{\\\"ms\\\":9516,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":653,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":13,\\\"user_id\\\":57,\\\"target_id\\\":56,\\\"relation_type\\\":\\\"ceo\\\",\\\"answers_count\\\":12,\\\"items_count_server\\\":12,\\\"items_count_client\\\":12,\\\"server_received_at\\\":\\\"2025-09-03T15:08:19+02:00\\\",\\\"client_started_at\\\":\\\"2025-09-03T13:08:07.119Z\\\",\\\"client_finished_at\\\":\\\"2025-09-03T13:08:17.780Z\\\",\\\"measurement_uuid\\\":\\\"68c24406-5822-450f-8714-5f0f509e41ce\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":12,\\\"value_counts\\\":{\\\"5\\\":4,\\\"6\\\":4,\\\"4\\\":2,\\\"3\\\":1,\\\"7\\\":1},\\\"dominant_value\\\":5,\\\"dominant_share\\\":0.333000000000000018207657603852567262947559356689453125,\\\"extremes_share\\\":0.08300000000000000432986979603811050765216350555419921875,\\\"all_same_value\\\":false,\\\"mean_percent\\\":73.81000000000000227373675443232059478759765625,\\\"stddev_percent\\\":15.25},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":1,\\\"fast_clicks_p1000\\\":0.81799999999999994937383007709286175668239593505859375,\\\"pace_cv\\\":0.32772559236654341674466195399872958660125732421875,\\\"pace_median_ms\\\":822,\\\"pace_iqr_ms\\\":[420,924],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.333000000000000018207657603852567262947559356689453125,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":1,\\\"assessment_span\\\":\\\"current_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":69,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":7,\\\"window_days\\\":3,\\\"tier\\\":\\\"medium\\\",\\\"guidance\\\":\\\"balanced\\\",\\\"relation_mix\\\":{\\\"ceo\\\":5,\\\"self\\\":2},\\\"device_mix\\\":{\\\"desktop\\\":7},\\\"trust_summary\\\":{\\\"median\\\":9,\\\"iqr\\\":[8,10],\\\"low_rate\\\":0.14000000000000001332267629550187848508358001708984375,\\\"high_rate\\\":0,\\\"trend\\\":\\\"up\\\"},\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"fast_read\\\",\\\"rate\\\":0.289999999999999980015985556747182272374629974365234375}],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":961,\\\"iqr\\\":[792,989],\\\"coverage\\\":7},\\\"uniform_ratio\\\":{\\\"median\\\":0.467000000000000026201263381153694353997707366943359375,\\\"iqr\\\":[0.2220000000000000028865798640254070051014423370361328125,0.55600000000000004973799150320701301097869873046875],\\\"coverage\\\":7},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.299999999999999988897769753748434595763683319091796875,\\\"iqr\\\":[0,0.5],\\\"coverage\\\":7},\\\"fast_pass_rate\\\":{\\\"median\\\":1,\\\"iqr\\\":[0.8329999999999999626965063725947402417659759521484375,1],\\\"coverage\\\":7}},\\\"by_relation\\\":{\\\"ceo\\\":{\\\"n\\\":5,\\\"trust_median\\\":9,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]},\\\"self\\\":{\\\"n\\\":2,\\\"trust_median\\\":9,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]}},\\\"for_current_target\\\":null}}\"'),
(13,57,57,'2025-09-03 15:06:37','{\"trust_score\":9,\"trust_index\":45,\"flags\":[\"too_fast\",\"fast_read\",\"one_click_fast_read\"],\"rationale\":\"one_click_rate=1.00, fast_pass_rate=1, short_read_rate=0.667 → low-effort speeding and one-click response; reading_speed_median_100ch=802 excludes fast_read but short_read_rate triggers fast_read flag per rules; baseline unavailable so no inflation/deflation adjustment; content_stats dominant_share=0.222, uniform_ratio=0.222, zigzag_index=0 indicates no uniform or extremes-only pattern; pace_cv=0.367 moderate variability but one_click_all=true signals low effort; history for user shows previous similar flags and low trust median=10 consistent with current score; applying slight penalty (−3) from neutral 12 to 9 to reflect low-effort combined with speeding and one-click fast read behavior; no incomplete_scroll or suspicious pattern flags; trust_score clamped and trust_index=round(9*5)=45 in\",\"relation_type\":\"self\",\"target_id\":57,\"ai_timestamp\":\"2025-09-03T15:10:00+02:00\",\"features_snapshot\":{\"avg_ms_per_item\":961,\"uniform_ratio\":0.2220000000000000028865798640254070051014423370361328125,\"entropy\":null,\"zigzag_index\":0,\"fast_pass_rate\":1,\"device_type\":\"desktop\",\"pace_cv\":0.36699999999999999289457264239899814128875732421875,\"pace_median_ms\":587,\"pace_iqr_ms\":[536,1148],\"one_click_rate\":1,\"active_ratio\":1,\"short_read_rate\":0.6670000000000000373034936274052597582340240478515625,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":802,\"fast_read_rate_100ch\":0},\"baseline_echo\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"721992e5-b660-45ff-88f2-d595e17bebc8\\\",\\\"started_at\\\":\\\"2025-09-03T13:06:27.759Z\\\",\\\"finished_at\\\":\\\"2025-09-03T13:06:36.406Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":1278,\\\"viewport_h\\\":944},\\\"items_count\\\":9,\\\"display_order\\\":[17,18,19,21,24,23,22,25,51],\\\"total_ms\\\":8646,\\\"visible_ms\\\":8646,\\\"active_ms\\\":8646,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":9,\\\"keydowns\\\":0,\\\"scrolls\\\":56,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":9},\\\"items\\\":[{\\\"question_id\\\":17,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":82,\\\"view_read_ms\\\":647,\\\"view_read_ms_per_100ch\\\":789,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":22,\\\"first_interaction_ms\\\":669,\\\"value_path\\\":[{\\\"ms\\\":669,\\\"v\\\":2}],\\\"last_value\\\":2,\\\"changes_count\\\":0,\\\"focus_ms\\\":416,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":18,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":91,\\\"view_read_ms\\\":1220,\\\"view_read_ms_per_100ch\\\":1341,\\\"seq_read_ms_raw\\\":573,\\\"seq_read_ms_active\\\":573,\\\"seq_read_ms_per_100ch\\\":630,\\\"first_seen_ms\\\":22,\\\"first_interaction_ms\\\":1242,\\\"value_path\\\":[{\\\"ms\\\":1242,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":782,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":19,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":97,\\\"view_read_ms\\\":1756,\\\"view_read_ms_per_100ch\\\":1810,\\\"seq_read_ms_raw\\\":536,\\\"seq_read_ms_active\\\":536,\\\"seq_read_ms_per_100ch\\\":553,\\\"first_seen_ms\\\":22,\\\"first_interaction_ms\\\":1778,\\\"value_path\\\":[{\\\"ms\\\":1778,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":467,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":21,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":68,\\\"view_read_ms\\\":2268,\\\"view_read_ms_per_100ch\\\":3335,\\\"seq_read_ms_raw\\\":512,\\\"seq_read_ms_active\\\":512,\\\"seq_read_ms_per_100ch\\\":753,\\\"first_seen_ms\\\":22,\\\"first_interaction_ms\\\":2290,\\\"value_path\\\":[{\\\"ms\\\":2290,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":752,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":24,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":76,\\\"view_read_ms\\\":827,\\\"view_read_ms_per_100ch\\\":1088,\\\"seq_read_ms_raw\\\":1166,\\\"seq_read_ms_active\\\":1166,\\\"seq_read_ms_per_100ch\\\":1534,\\\"first_seen_ms\\\":2629,\\\"first_interaction_ms\\\":3456,\\\"value_path\\\":[{\\\"ms\\\":3456,\\\"v\\\":7}],\\\"last_value\\\":7,\\\"changes_count\\\":0,\\\"focus_ms\\\":661,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":23,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":63,\\\"view_read_ms\\\":1398,\\\"view_read_ms_per_100ch\\\":2219,\\\"seq_read_ms_raw\\\":598,\\\"seq_read_ms_active\\\":598,\\\"seq_read_ms_per_100ch\\\":949,\\\"first_seen_ms\\\":2656,\\\"first_interaction_ms\\\":4054,\\\"value_path\\\":[{\\\"ms\\\":4054,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":853,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":22,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":133,\\\"view_read_ms\\\":2489,\\\"view_read_ms_per_100ch\\\":1871,\\\"seq_read_ms_raw\\\":1132,\\\"seq_read_ms_active\\\":1132,\\\"seq_read_ms_per_100ch\\\":851,\\\"first_seen_ms\\\":2697,\\\"first_interaction_ms\\\":5186,\\\"value_path\\\":[{\\\"ms\\\":5186,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":1005,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":25,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":87,\\\"view_read_ms\\\":1266,\\\"view_read_ms_per_100ch\\\":1455,\\\"seq_read_ms_raw\\\":576,\\\"seq_read_ms_active\\\":576,\\\"seq_read_ms_per_100ch\\\":662,\\\"first_seen_ms\\\":4496,\\\"first_interaction_ms\\\":5762,\\\"value_path\\\":[{\\\"ms\\\":5762,\\\"v\\\":3}],\\\"last_value\\\":3,\\\"changes_count\\\":0,\\\"focus_ms\\\":813,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":51,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":41,\\\"view_read_ms\\\":854,\\\"view_read_ms_per_100ch\\\":2083,\\\"seq_read_ms_raw\\\":1148,\\\"seq_read_ms_active\\\":1148,\\\"seq_read_ms_per_100ch\\\":2800,\\\"first_seen_ms\\\":6056,\\\"first_interaction_ms\\\":6910,\\\"value_path\\\":[{\\\"ms\\\":6910,\\\"v\\\":3}],\\\"last_value\\\":3,\\\"changes_count\\\":0,\\\"focus_ms\\\":1200,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":13,\\\"user_id\\\":57,\\\"target_id\\\":57,\\\"relation_type\\\":\\\"self\\\",\\\"answers_count\\\":9,\\\"items_count_server\\\":9,\\\"items_count_client\\\":9,\\\"server_received_at\\\":\\\"2025-09-03T15:06:37+02:00\\\",\\\"client_started_at\\\":\\\"2025-09-03T13:06:27.759Z\\\",\\\"client_finished_at\\\":\\\"2025-09-03T13:06:36.406Z\\\",\\\"measurement_uuid\\\":\\\"721992e5-b660-45ff-88f2-d595e17bebc8\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":9,\\\"value_counts\\\":{\\\"4\\\":2,\\\"6\\\":2,\\\"7\\\":2,\\\"3\\\":2,\\\"2\\\":1},\\\"dominant_value\\\":4,\\\"dominant_share\\\":0.2220000000000000028865798640254070051014423370361328125,\\\"extremes_share\\\":0.2220000000000000028865798640254070051014423370361328125,\\\"all_same_value\\\":false,\\\"mean_percent\\\":66.6700000000000017053025658242404460906982421875,\\\"stddev_percent\\\":25.199999999999999289457264239899814128875732421875},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":1,\\\"fast_clicks_p1000\\\":0.625,\\\"pace_cv\\\":0.367420738534956825560584547929465770721435546875,\\\"pace_median_ms\\\":587,\\\"pace_iqr_ms\\\":[536,1148],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.6670000000000000373034936274052597582340240478515625,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":0,\\\"assessment_span\\\":\\\"previous_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":63.6700000000000017053025658242404460906982421875,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":3,\\\"window_days\\\":0,\\\"tier\\\":\\\"cold_start\\\",\\\"guidance\\\":\\\"be_kind\\\",\\\"relation_mix\\\":{\\\"self\\\":1,\\\"ceo\\\":2},\\\"device_mix\\\":{\\\"desktop\\\":3},\\\"trust_summary\\\":{\\\"median\\\":10,\\\"iqr\\\":[9,12],\\\"low_rate\\\":0,\\\"high_rate\\\":0,\\\"trend\\\":\\\"up\\\"},\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"fast_read\\\",\\\"rate\\\":0.330000000000000015543122344752191565930843353271484375}],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":989,\\\"iqr\\\":[987,1078],\\\"coverage\\\":3},\\\"uniform_ratio\\\":{\\\"median\\\":0.55600000000000004973799150320701301097869873046875,\\\"iqr\\\":[0.416999999999999981792342396147432737052440643310546875,0.6670000000000000373034936274052597582340240478515625],\\\"coverage\\\":3},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.299999999999999988897769753748434595763683319091796875,\\\"iqr\\\":[0.1429999999999999882316359389733406715095043182373046875,0.5],\\\"coverage\\\":3},\\\"fast_pass_rate\\\":{\\\"median\\\":1,\\\"iqr\\\":[0.90000000000000002220446049250313080847263336181640625,1],\\\"coverage\\\":3}},\\\"by_relation\\\":{\\\"self\\\":{\\\"n\\\":1,\\\"trust_median\\\":10,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]},\\\"ceo\\\":{\\\"n\\\":2,\\\"trust_median\\\":9,\\\"flags_top\\\":[{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1}]}},\\\"for_current_target\\\":null}}\"'),
(13,57,58,'2025-09-03 15:07:56','{\"trust_score\":10,\"trust_index\":50,\"flags\":[\"too_fast\",\"one_click_fast_read\"],\"rationale\":\"one_click_rate=1.00 and fast_pass_rate=1.00 indicate \'too_fast\' and \'one_click_fast_read\'. However, reading_speed_median_100ch=1074 (>400) and short_read_rate=0.417 (<0.60) means no \'fast_read\' flag. Content_stats shows dominant_share=0.50, uniform_ratio=0.50, zigzag_index=0.30, stddev_percent=9.82, pace_cv=0.51, all indicating moderate variability and no central tendency or extremes-only pattern. Baseline unavailable so no leniency/severity bias adjustment. History shows similar flags (too_fast and one_click_fast_read) with trust_median=9 and an upward trend. Given strict speed-related flags balanced by moderate pacing and careful reading time, initial neutral score 11 minus 2 for speed/one-click lowering score to 9, adjusted to 10 to align with history\'s upward trend and balanced caution\",\"relation_type\":\"ceo\",\"target_id\":58,\"ai_timestamp\":\"2025-09-03T15:10:00Z\",\"features_snapshot\":{\"avg_ms_per_item\":866,\"uniform_ratio\":0.5,\"entropy\":null,\"zigzag_index\":0.299999999999999988897769753748434595763683319091796875,\"fast_pass_rate\":1,\"device_type\":\"desktop\",\"pace_cv\":0.51300000000000001154631945610162802040576934814453125,\"pace_median_ms\":870,\"pace_iqr_ms\":[318,1133],\"one_click_rate\":1,\"active_ratio\":1,\"short_read_rate\":0.416999999999999981792342396147432737052440643310546875,\"incomplete_scroll\":false,\"reading_speed_median_100ch\":1074,\"fast_read_rate_100ch\":0.181999999999999995115018691649311222136020660400390625},\"baseline_echo\":null}','\"{\\\"client\\\":{\\\"measurement_uuid\\\":\\\"34b05347-87f2-4742-a5e4-97bd31bd11a5\\\",\\\"started_at\\\":\\\"2025-09-03T13:07:45.180Z\\\",\\\"finished_at\\\":\\\"2025-09-03T13:07:55.567Z\\\",\\\"tz_offset_min\\\":120,\\\"device\\\":{\\\"type\\\":\\\"desktop\\\",\\\"dpr\\\":1,\\\"viewport_w\\\":1278,\\\"viewport_h\\\":944},\\\"items_count\\\":12,\\\"display_order\\\":[8,9,46,10,11,49,48,15,27,43,42,51],\\\"total_ms\\\":10386,\\\"visible_ms\\\":10386,\\\"active_ms\\\":10386,\\\"visibility_events\\\":{\\\"hidden_count\\\":0,\\\"visible_count\\\":1},\\\"focus_events\\\":{\\\"focus_count\\\":1,\\\"blur_count\\\":0},\\\"interactions\\\":{\\\"clicks\\\":12,\\\"keydowns\\\":0,\\\"scrolls\\\":78,\\\"contextmenus\\\":0,\\\"pastes\\\":0},\\\"scroll_sections_seen\\\":{\\\"min_index\\\":1,\\\"max_index\\\":12},\\\"items\\\":[{\\\"question_id\\\":8,\\\"index\\\":1,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":56,\\\"view_read_ms\\\":745,\\\"view_read_ms_per_100ch\\\":1330,\\\"seq_read_ms_raw\\\":null,\\\"seq_read_ms_active\\\":null,\\\"seq_read_ms_per_100ch\\\":null,\\\"first_seen_ms\\\":25,\\\"first_interaction_ms\\\":770,\\\"value_path\\\":[{\\\"ms\\\":770,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":461,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":9,\\\"index\\\":2,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":83,\\\"view_read_ms\\\":1878,\\\"view_read_ms_per_100ch\\\":2263,\\\"seq_read_ms_raw\\\":1133,\\\"seq_read_ms_active\\\":1133,\\\"seq_read_ms_per_100ch\\\":1365,\\\"first_seen_ms\\\":25,\\\"first_interaction_ms\\\":1903,\\\"value_path\\\":[{\\\"ms\\\":1903,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":600,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":46,\\\"index\\\":3,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":36,\\\"view_read_ms\\\":2244,\\\"view_read_ms_per_100ch\\\":6233,\\\"seq_read_ms_raw\\\":366,\\\"seq_read_ms_active\\\":366,\\\"seq_read_ms_per_100ch\\\":1017,\\\"first_seen_ms\\\":25,\\\"first_interaction_ms\\\":2269,\\\"value_path\\\":[{\\\"ms\\\":2269,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":1233,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":10,\\\"index\\\":4,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":81,\\\"view_read_ms\\\":3114,\\\"view_read_ms_per_100ch\\\":3844,\\\"seq_read_ms_raw\\\":870,\\\"seq_read_ms_active\\\":870,\\\"seq_read_ms_per_100ch\\\":1074,\\\"first_seen_ms\\\":25,\\\"first_interaction_ms\\\":3139,\\\"value_path\\\":[{\\\"ms\\\":3139,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":315,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":11,\\\"index\\\":5,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":79,\\\"view_read_ms\\\":861,\\\"view_read_ms_per_100ch\\\":1090,\\\"seq_read_ms_raw\\\":318,\\\"seq_read_ms_active\\\":318,\\\"seq_read_ms_per_100ch\\\":403,\\\"first_seen_ms\\\":2596,\\\"first_interaction_ms\\\":3457,\\\"value_path\\\":[{\\\"ms\\\":3457,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":519,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":49,\\\"index\\\":6,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":43,\\\"view_read_ms\\\":2196,\\\"view_read_ms_per_100ch\\\":5107,\\\"seq_read_ms_raw\\\":1420,\\\"seq_read_ms_active\\\":1420,\\\"seq_read_ms_per_100ch\\\":3302,\\\"first_seen_ms\\\":2681,\\\"first_interaction_ms\\\":4877,\\\"value_path\\\":[{\\\"ms\\\":4877,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":502,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":48,\\\"index\\\":7,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":85,\\\"view_read_ms\\\":1346,\\\"view_read_ms_per_100ch\\\":1584,\\\"seq_read_ms_raw\\\":256,\\\"seq_read_ms_active\\\":256,\\\"seq_read_ms_per_100ch\\\":301,\\\"first_seen_ms\\\":3787,\\\"first_interaction_ms\\\":5133,\\\"value_path\\\":[{\\\"ms\\\":5133,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":717,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":15,\\\"index\\\":8,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":98,\\\"view_read_ms\\\":1887,\\\"view_read_ms_per_100ch\\\":1926,\\\"seq_read_ms_raw\\\":568,\\\"seq_read_ms_active\\\":568,\\\"seq_read_ms_per_100ch\\\":580,\\\"first_seen_ms\\\":3814,\\\"first_interaction_ms\\\":5701,\\\"value_path\\\":[{\\\"ms\\\":5701,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":955,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":27,\\\"index\\\":9,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":54,\\\"view_read_ms\\\":768,\\\"view_read_ms_per_100ch\\\":1422,\\\"seq_read_ms_raw\\\":962,\\\"seq_read_ms_active\\\":962,\\\"seq_read_ms_per_100ch\\\":1781,\\\"first_seen_ms\\\":5895,\\\"first_interaction_ms\\\":6663,\\\"value_path\\\":[{\\\"ms\\\":6663,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":412,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":43,\\\"index\\\":10,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":85,\\\"view_read_ms\\\":997,\\\"view_read_ms_per_100ch\\\":1173,\\\"seq_read_ms_raw\\\":335,\\\"seq_read_ms_active\\\":335,\\\"seq_read_ms_per_100ch\\\":394,\\\"first_seen_ms\\\":6001,\\\"first_interaction_ms\\\":6998,\\\"value_path\\\":[{\\\"ms\\\":6998,\\\"v\\\":6}],\\\"last_value\\\":6,\\\"changes_count\\\":0,\\\"focus_ms\\\":581,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":42,\\\"index\\\":11,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":47,\\\"view_read_ms\\\":2060,\\\"view_read_ms_per_100ch\\\":4383,\\\"seq_read_ms_raw\\\":1103,\\\"seq_read_ms_active\\\":1103,\\\"seq_read_ms_per_100ch\\\":2347,\\\"first_seen_ms\\\":6041,\\\"first_interaction_ms\\\":8101,\\\"value_path\\\":[{\\\"ms\\\":8101,\\\"v\\\":5}],\\\"last_value\\\":5,\\\"changes_count\\\":0,\\\"focus_ms\\\":846,\\\"attention_check\\\":{\\\"present\\\":false}},{\\\"question_id\\\":51,\\\"index\\\":12,\\\"scale\\\":{\\\"min\\\":0,\\\"max\\\":7,\\\"step\\\":1},\\\"chars\\\":26,\\\"view_read_ms\\\":1828,\\\"view_read_ms_per_100ch\\\":7031,\\\"seq_read_ms_raw\\\":1156,\\\"seq_read_ms_active\\\":1156,\\\"seq_read_ms_per_100ch\\\":4446,\\\"first_seen_ms\\\":7429,\\\"first_interaction_ms\\\":9257,\\\"value_path\\\":[{\\\"ms\\\":9257,\\\"v\\\":4}],\\\"last_value\\\":4,\\\"changes_count\\\":0,\\\"focus_ms\\\":1009,\\\"attention_check\\\":{\\\"present\\\":false}}]},\\\"server_context\\\":{\\\"org_id\\\":1,\\\"assessment_id\\\":13,\\\"user_id\\\":57,\\\"target_id\\\":58,\\\"relation_type\\\":\\\"ceo\\\",\\\"answers_count\\\":12,\\\"items_count_server\\\":12,\\\"items_count_client\\\":12,\\\"server_received_at\\\":\\\"2025-09-03T15:07:56+02:00\\\",\\\"client_started_at\\\":\\\"2025-09-03T13:07:45.180Z\\\",\\\"client_finished_at\\\":\\\"2025-09-03T13:07:55.567Z\\\",\\\"measurement_uuid\\\":\\\"34b05347-87f2-4742-a5e4-97bd31bd11a5\\\",\\\"tz_offset_min\\\":120,\\\"version\\\":\\\"t1.0\\\"},\\\"content_stats\\\":{\\\"items_count\\\":12,\\\"value_counts\\\":{\\\"5\\\":6,\\\"6\\\":4,\\\"4\\\":2},\\\"dominant_value\\\":5,\\\"dominant_share\\\":0.5,\\\"extremes_share\\\":0,\\\"all_same_value\\\":false,\\\"mean_percent\\\":73.81000000000000227373675443232059478759765625,\\\"stddev_percent\\\":9.82000000000000028421709430404007434844970703125},\\\"features\\\":{\\\"all_same_value\\\":false,\\\"extremes_only\\\":false,\\\"count_mismatch\\\":false,\\\"too_fast_total\\\":false,\\\"fast_clicks_p1500\\\":1,\\\"fast_clicks_p1000\\\":0.63600000000000000976996261670137755572795867919921875,\\\"pace_cv\\\":0.512922318518377551299636252224445343017578125,\\\"pace_median_ms\\\":870,\\\"pace_iqr_ms\\\":[318,1133],\\\"one_click_rate\\\":1,\\\"one_click_all\\\":true,\\\"short_read_p1500\\\":0.416999999999999981792342396147432737052440643310546875,\\\"active_ratio\\\":1,\\\"incomplete_scroll\\\":false,\\\"too_fast_burst\\\":true,\\\"suspicious_metronome\\\":false,\\\"suspicious_one_click\\\":true},\\\"baseline\\\":{\\\"available\\\":false,\\\"raters_total\\\":0,\\\"assessment_span\\\":\\\"previous_insufficient\\\",\\\"method\\\":\\\"n\\/a\\\",\\\"mean_100\\\":null,\\\"current_mean_100\\\":73.25,\\\"delta_mean\\\":null},\\\"history_digest\\\":{\\\"n\\\":6,\\\"window_days\\\":3,\\\"tier\\\":\\\"medium\\\",\\\"guidance\\\":\\\"balanced\\\",\\\"relation_mix\\\":{\\\"ceo\\\":4,\\\"self\\\":2},\\\"device_mix\\\":{\\\"desktop\\\":6},\\\"trust_summary\\\":{\\\"median\\\":9,\\\"iqr\\\":[8,10],\\\"low_rate\\\":0.1700000000000000122124532708767219446599483489990234375,\\\"high_rate\\\":0,\\\"trend\\\":\\\"up\\\"},\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"fast_read\\\",\\\"rate\\\":0.330000000000000015543122344752191565930843353271484375}],\\\"features_summary\\\":{\\\"avg_ms_per_item_ms\\\":{\\\"median\\\":974,\\\"iqr\\\":[792,989],\\\"coverage\\\":6},\\\"uniform_ratio\\\":{\\\"median\\\":0.442000000000000003996802888650563545525074005126953125,\\\"iqr\\\":[0.2220000000000000028865798640254070051014423370361328125,0.55600000000000004973799150320701301097869873046875],\\\"coverage\\\":6},\\\"entropy\\\":{\\\"median\\\":null,\\\"iqr\\\":[null,null],\\\"coverage\\\":0},\\\"zigzag_index\\\":{\\\"median\\\":0.265500000000000013766765505351941101253032684326171875,\\\"iqr\\\":[0,0.5],\\\"coverage\\\":6},\\\"fast_pass_rate\\\":{\\\"median\\\":0.96450000000000002398081733190338127315044403076171875,\\\"iqr\\\":[0.8329999999999999626965063725947402417659759521484375,1],\\\"coverage\\\":6}},\\\"by_relation\\\":{\\\"ceo\\\":{\\\"n\\\":4,\\\"trust_median\\\":9,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]},\\\"self\\\":{\\\"n\\\":2,\\\"trust_median\\\":9,\\\"flags_top\\\":[{\\\"flag\\\":\\\"too_fast\\\",\\\"rate\\\":1},{\\\"flag\\\":\\\"one_click_fast_read\\\",\\\"rate\\\":1}]}},\\\"for_current_target\\\":null}}\"'),
(17,59,59,'2025-09-27 18:34:59',NULL,'{\"client\":{\"measurement_uuid\":\"97cf4527-23bb-4f2f-aa9f-da991b9c7848\",\"started_at\":\"2025-09-27T16:34:38.964Z\",\"finished_at\":\"2025-09-27T16:34:58.617Z\",\"tz_offset_min\":120,\"device\":{\"type\":\"desktop\",\"dpr\":1,\"viewport_w\":2560,\"viewport_h\":945},\"items_count\":15,\"display_order\":[29,26,1,28,50,32,41,34,48,15,49,22,25,23,24],\"total_ms\":19653,\"visible_ms\":19653,\"active_ms\":19653,\"visibility_events\":{\"hidden_count\":0,\"visible_count\":1},\"focus_events\":{\"focus_count\":1,\"blur_count\":0},\"interactions\":{\"clicks\":15,\"keydowns\":0,\"scrolls\":118,\"contextmenus\":0,\"pastes\":0},\"scroll_sections_seen\":{\"min_index\":1,\"max_index\":15},\"items\":[{\"question_id\":29,\"index\":1,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":69,\"view_read_ms\":8712,\"view_read_ms_per_100ch\":12626,\"seq_read_ms_raw\":null,\"seq_read_ms_active\":null,\"seq_read_ms_per_100ch\":null,\"first_seen_ms\":13,\"first_interaction_ms\":8725,\"value_path\":[{\"ms\":8725,\"v\":7}],\"last_value\":7,\"changes_count\":0,\"focus_ms\":7548,\"attention_check\":{\"present\":false}},{\"question_id\":26,\"index\":2,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":56,\"view_read_ms\":9333,\"view_read_ms_per_100ch\":16666,\"seq_read_ms_raw\":621,\"seq_read_ms_active\":622,\"seq_read_ms_per_100ch\":1111,\"first_seen_ms\":13,\"first_interaction_ms\":9346,\"value_path\":[{\"ms\":9346,\"v\":6}],\"last_value\":6,\"changes_count\":0,\"focus_ms\":880,\"attention_check\":{\"present\":false}},{\"question_id\":1,\"index\":3,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":75,\"view_read_ms\":10421,\"view_read_ms_per_100ch\":13895,\"seq_read_ms_raw\":1088,\"seq_read_ms_active\":1088,\"seq_read_ms_per_100ch\":1451,\"first_seen_ms\":13,\"first_interaction_ms\":10434,\"value_path\":[{\"ms\":10434,\"v\":5}],\"last_value\":5,\"changes_count\":0,\"focus_ms\":672,\"attention_check\":{\"present\":false}},{\"question_id\":28,\"index\":4,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":94,\"view_read_ms\":10817,\"view_read_ms_per_100ch\":11507,\"seq_read_ms_raw\":396,\"seq_read_ms_active\":396,\"seq_read_ms_per_100ch\":421,\"first_seen_ms\":13,\"first_interaction_ms\":10830,\"value_path\":[{\"ms\":10830,\"v\":6}],\"last_value\":6,\"changes_count\":0,\"focus_ms\":885,\"attention_check\":{\"present\":false}},{\"question_id\":50,\"index\":5,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":68,\"view_read_ms\":1784,\"view_read_ms_per_100ch\":2624,\"seq_read_ms_raw\":794,\"seq_read_ms_active\":794,\"seq_read_ms_per_100ch\":1168,\"first_seen_ms\":9840,\"first_interaction_ms\":11624,\"value_path\":[{\"ms\":11624,\"v\":5}],\"last_value\":5,\"changes_count\":0,\"focus_ms\":427,\"attention_check\":{\"present\":false}},{\"question_id\":32,\"index\":6,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":110,\"view_read_ms\":2359,\"view_read_ms_per_100ch\":2145,\"seq_read_ms_raw\":654,\"seq_read_ms_active\":654,\"seq_read_ms_per_100ch\":595,\"first_seen_ms\":9919,\"first_interaction_ms\":12278,\"value_path\":[{\"ms\":12278,\"v\":6}],\"last_value\":6,\"changes_count\":0,\"focus_ms\":729,\"attention_check\":{\"present\":false}},{\"question_id\":41,\"index\":7,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":80,\"view_read_ms\":1601,\"view_read_ms_per_100ch\":2001,\"seq_read_ms_raw\":482,\"seq_read_ms_active\":482,\"seq_read_ms_per_100ch\":603,\"first_seen_ms\":11159,\"first_interaction_ms\":12760,\"value_path\":[{\"ms\":12760,\"v\":5}],\"last_value\":5,\"changes_count\":0,\"focus_ms\":607,\"attention_check\":{\"present\":false}},{\"question_id\":34,\"index\":8,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":78,\"view_read_ms\":2327,\"view_read_ms_per_100ch\":2983,\"seq_read_ms_raw\":792,\"seq_read_ms_active\":792,\"seq_read_ms_per_100ch\":1015,\"first_seen_ms\":11225,\"first_interaction_ms\":13552,\"value_path\":[{\"ms\":13552,\"v\":6}],\"last_value\":6,\"changes_count\":0,\"focus_ms\":307,\"attention_check\":{\"present\":false}},{\"question_id\":48,\"index\":9,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":76,\"view_read_ms\":1286,\"view_read_ms_per_100ch\":1692,\"seq_read_ms_raw\":762,\"seq_read_ms_active\":762,\"seq_read_ms_per_100ch\":1003,\"first_seen_ms\":13028,\"first_interaction_ms\":14314,\"value_path\":[{\"ms\":14314,\"v\":7}],\"last_value\":7,\"changes_count\":0,\"focus_ms\":493,\"attention_check\":{\"present\":false}},{\"question_id\":15,\"index\":10,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":104,\"view_read_ms\":887,\"view_read_ms_per_100ch\":853,\"seq_read_ms_raw\":344,\"seq_read_ms_active\":344,\"seq_read_ms_per_100ch\":331,\"first_seen_ms\":13771,\"first_interaction_ms\":14658,\"value_path\":[{\"ms\":14658,\"v\":7}],\"last_value\":7,\"changes_count\":0,\"focus_ms\":889,\"attention_check\":{\"present\":false}},{\"question_id\":49,\"index\":11,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":44,\"view_read_ms\":1729,\"view_read_ms_per_100ch\":3930,\"seq_read_ms_raw\":964,\"seq_read_ms_active\":964,\"seq_read_ms_per_100ch\":2191,\"first_seen_ms\":13893,\"first_interaction_ms\":15622,\"value_path\":[{\"ms\":15622,\"v\":6}],\"last_value\":6,\"changes_count\":0,\"focus_ms\":229,\"attention_check\":{\"present\":false}},{\"question_id\":22,\"index\":12,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":133,\"view_read_ms\":1129,\"view_read_ms_per_100ch\":849,\"seq_read_ms_raw\":426,\"seq_read_ms_active\":426,\"seq_read_ms_per_100ch\":320,\"first_seen_ms\":14919,\"first_interaction_ms\":16048,\"value_path\":[{\"ms\":16048,\"v\":6}],\"last_value\":6,\"changes_count\":0,\"focus_ms\":778,\"attention_check\":{\"present\":false}},{\"question_id\":25,\"index\":13,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":87,\"view_read_ms\":1859,\"view_read_ms_per_100ch\":2137,\"seq_read_ms_raw\":770,\"seq_read_ms_active\":770,\"seq_read_ms_per_100ch\":885,\"first_seen_ms\":14959,\"first_interaction_ms\":16818,\"value_path\":[{\"ms\":16818,\"v\":6}],\"last_value\":6,\"changes_count\":0,\"focus_ms\":253,\"attention_check\":{\"present\":false}},{\"question_id\":23,\"index\":14,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":63,\"view_read_ms\":1125,\"view_read_ms_per_100ch\":1786,\"seq_read_ms_raw\":600,\"seq_read_ms_active\":600,\"seq_read_ms_per_100ch\":952,\"first_seen_ms\":16293,\"first_interaction_ms\":17418,\"value_path\":[{\"ms\":17418,\"v\":7}],\"last_value\":7,\"changes_count\":0,\"focus_ms\":856,\"attention_check\":{\"present\":false}},{\"question_id\":24,\"index\":15,\"scale\":{\"min\":0,\"max\":7,\"step\":1},\"chars\":76,\"view_read_ms\":2065,\"view_read_ms_per_100ch\":2717,\"seq_read_ms_raw\":980,\"seq_read_ms_active\":980,\"seq_read_ms_per_100ch\":1289,\"first_seen_ms\":16333,\"first_interaction_ms\":18398,\"value_path\":[{\"ms\":18398,\"v\":7}],\"last_value\":7,\"changes_count\":0,\"focus_ms\":835,\"attention_check\":{\"present\":false}}]},\"server_context\":{\"org_id\":5,\"assessment_id\":17,\"user_id\":59,\"target_id\":59,\"relation_type\":\"self\",\"answers_count\":15,\"items_count_server\":15,\"items_count_client\":15,\"server_received_at\":\"2025-09-27T18:34:59+02:00\",\"client_started_at\":\"2025-09-27T16:34:38.964Z\",\"client_finished_at\":\"2025-09-27T16:34:58.617Z\",\"measurement_uuid\":\"97cf4527-23bb-4f2f-aa9f-da991b9c7848\",\"tz_offset_min\":120,\"version\":\"t1.0\"},\"content_stats\":{\"items_count\":15,\"value_counts\":{\"6\":7,\"7\":5,\"5\":3},\"dominant_value\":6,\"dominant_share\":0.467000000000000026201263381153694353997707366943359375,\"extremes_share\":0.333000000000000018207657603852567262947559356689453125,\"all_same_value\":false,\"mean_percent\":87.6200000000000045474735088646411895751953125,\"stddev_percent\":10.2599999999999997868371792719699442386627197265625},\"features\":{\"all_same_value\":false,\"extremes_only\":false,\"count_mismatch\":false,\"too_fast_total\":false,\"fast_clicks_p1500\":1,\"fast_clicks_p1000\":0.9290000000000000479616346638067625463008880615234375,\"pace_cv\":0.319828276519199372618373899967991746962070465087890625,\"pace_median_ms\":708,\"pace_iqr_ms\":[426,794],\"one_click_rate\":1,\"one_click_all\":true,\"short_read_p1500\":0.26700000000000001509903313490212894976139068603515625,\"active_ratio\":1,\"incomplete_scroll\":false,\"too_fast_burst\":true,\"suspicious_metronome\":false,\"suspicious_one_click\":true},\"baseline\":{\"available\":false,\"raters_total\":0,\"assessment_span\":\"none\",\"method\":\"n\\/a\",\"mean_100\":null,\"current_mean_100\":88,\"delta_mean\":null},\"history_digest\":{\"n\":0,\"tier\":\"cold_start\",\"guidance\":\"be_kind\",\"message\":\"Nincs kor\\u00e1bbi AI-\\u00e9rt\\u00e9kel\\u00e9s. \\u00daj felhaszn\\u00e1l\\u00f3 \\u2013 legy\\u00fcnk k\\u00edm\\u00e9letesek.\"}}');
/*!40000 ALTER TABLE `user_competency_submit` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_user_comp_submit_bi` BEFORE INSERT ON `user_competency_submit` FOR EACH ROW BEGIN
  DECLARE v_org BIGINT UNSIGNED;
  SELECT organization_id INTO v_org FROM assessment WHERE id = NEW.assessment_id LIMIT 1;

  IF v_org IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assessment has no organization';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM organization_user ou WHERE ou.organization_id = v_org AND ou.user_id = NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is not a member of the assessment organization';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM organization_user ou WHERE ou.organization_id = v_org AND ou.user_id = NEW.target_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'target_id is not a member of the assessment organization';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_user_comp_submit_bu` BEFORE UPDATE ON `user_competency_submit` FOR EACH ROW BEGIN
  DECLARE v_org BIGINT UNSIGNED;
  SELECT organization_id INTO v_org FROM assessment WHERE id = NEW.assessment_id LIMIT 1;

  IF v_org IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assessment has no organization';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM organization_user ou WHERE ou.organization_id = v_org AND ou.user_id = NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is not a member of the assessment organization';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM organization_user ou WHERE ou.organization_id = v_org AND ou.user_id = NEW.target_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'target_id is not a member of the assessment organization';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `user_connection`
--

DROP TABLE IF EXISTS `user_connection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_connection` (
  `survey_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  UNIQUE KEY `uq_user_connection` (`survey_id`,`question_id`,`user_id`,`target_id`),
  KEY `user_connection_fk1_idx` (`survey_id`),
  KEY `user_connection_fk2_idx` (`question_id`),
  KEY `user_connection_fk3_idx` (`user_id`),
  KEY `user_connection_fk4_idx` (`target_id`),
  CONSTRAINT `user_connection_fk1` FOREIGN KEY (`survey_id`) REFERENCES `connection_survey` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_connection_fk2` FOREIGN KEY (`question_id`) REFERENCES `connection_question` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_connection_fk3` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_connection_fk4` FOREIGN KEY (`target_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_connection`
--

LOCK TABLES `user_connection` WRITE;
/*!40000 ALTER TABLE `user_connection` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_connection` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_user_connection_bi` BEFORE INSERT ON `user_connection` FOR EACH ROW BEGIN
  DECLARE v_org BIGINT UNSIGNED;
  SELECT organization_id INTO v_org
  FROM connection_survey WHERE id = NEW.survey_id LIMIT 1;

  IF v_org IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Survey has no organization';
  END IF;

  IF NOT is_org_member(v_org, NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is not a member of the survey organization';
  END IF;

  IF NOT is_org_member(v_org, NEW.target_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'target_id is not a member of the survey organization';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_user_connection_bu` BEFORE UPDATE ON `user_connection` FOR EACH ROW BEGIN
  DECLARE v_org BIGINT UNSIGNED;
  SELECT organization_id INTO v_org
  FROM connection_survey WHERE id = NEW.survey_id LIMIT 1;

  IF v_org IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Survey has no organization';
  END IF;

  IF NOT is_org_member(v_org, NEW.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is not a member of the survey organization';
  END IF;

  IF NOT is_org_member(v_org, NEW.target_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'target_id is not a member of the survey organization';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `user_import_jobs`
--

DROP TABLE IF EXISTS `user_import_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_import_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `processed_rows` int(11) NOT NULL DEFAULT 0,
  `successful_rows` int(11) NOT NULL DEFAULT 0,
  `failed_rows` int(11) NOT NULL DEFAULT 0,
  `departments_created` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `error_report` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`error_report`)),
  `validation_report` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_report`)),
  `processing_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`processing_log`)),
  `send_emails` tinyint(1) NOT NULL DEFAULT 1,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_import_org` (`organization_id`),
  KEY `idx_import_created_by` (`created_by`),
  KEY `idx_import_status` (`status`),
  KEY `idx_import_created_at` (`created_at`),
  CONSTRAINT `fk_import_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_import_org` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_import_jobs`
--

LOCK TABLES `user_import_jobs` WRITE;
/*!40000 ALTER TABLE `user_import_jobs` DISABLE KEYS */;
INSERT INTO `user_import_jobs` VALUES
(1,1,1,'import_20251009131443.json','employee_import.xlsx',NULL,3,0,0,0,0,'failed','{\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1048 Column \'action_taken\' cannot be null (Connection: mysql, SQL: insert into `user_import_results` (`import_job_id`, `row_number`, `user_id`, `email`, `name`, `department_name`, `status`, `action_taken`, `error_message`) values (1, 1, ?, gipszj@nwbusiness.hu, Gipsz Jakab, ?, failed, ?, SQLSTATE[42S22]: Column not found: 1054 Unknown column \'wage\' in \'field list\' (Connection: mysql, SQL: insert into `user_wages` (`user_id`, `organization_id`, `wage`, `currency`, `created_at`) values (81, 1, 350000, HUF, 2025-10-09 13:14:43))))\",\"trace\":\"#0 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Database\\/Connection.php(779): Illuminate\\\\Database\\\\Connection->runQueryCallback(\'insert into `us...\', Array, Object(Closure))\\n#1 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Database\\/MySqlConnection.php(42): Illuminate\\\\Database\\\\Connection->run(\'insert into `us...\', Array, Object(Closure))\\n#2 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Database\\/Query\\/Builder.php(3717): Illuminate\\\\Database\\\\MySqlConnection->insert(\'insert into `us...\', Array)\\n#3 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Jobs\\/ProcessUserImport.php(165): Illuminate\\\\Database\\\\Query\\\\Builder->insert(Array)\\n#4 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Database\\/Concerns\\/ManagesTransactions.php(32): App\\\\Jobs\\\\ProcessUserImport->{closure:App\\\\Jobs\\\\ProcessUserImport::handle():80}(Object(Illuminate\\\\Database\\\\MySqlConnection))\\n#5 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Database\\/DatabaseManager.php(495): Illuminate\\\\Database\\\\Connection->transaction(Object(Closure))\\n#6 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Support\\/Facades\\/Facade.php(361): Illuminate\\\\Database\\\\DatabaseManager->__call(\'transaction\', Array)\\n#7 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Jobs\\/ProcessUserImport.php(80): Illuminate\\\\Support\\\\Facades\\\\Facade::__callStatic(\'transaction\', Array)\\n#8 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Container\\/BoundMethod.php(36): App\\\\Jobs\\\\ProcessUserImport->handle()\\n#9 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Container\\/Util.php(43): Illuminate\\\\Container\\\\BoundMethod::{closure:Illuminate\\\\Container\\\\BoundMethod::call():35}()\\n#10 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Container\\/BoundMethod.php(95): Illuminate\\\\Container\\\\Util::unwrapIfClosure(Object(Closure))\\n#11 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Container\\/BoundMethod.php(35): Illuminate\\\\Container\\\\BoundMethod::callBoundMethod(Object(Illuminate\\\\Foundation\\\\Application), Array, Object(Closure))\\n#12 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Container\\/Container.php(696): Illuminate\\\\Container\\\\BoundMethod::call(Object(Illuminate\\\\Foundation\\\\Application), Array, Array, NULL)\\n#13 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Bus\\/Dispatcher.php(126): Illuminate\\\\Container\\\\Container->call(Array)\\n#14 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(170): Illuminate\\\\Bus\\\\Dispatcher->{closure:Illuminate\\\\Bus\\\\Dispatcher::dispatchNow():123}(Object(App\\\\Jobs\\\\ProcessUserImport))\\n#15 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(127): Illuminate\\\\Pipeline\\\\Pipeline->{closure:Illuminate\\\\Pipeline\\\\Pipeline::prepareDestination():168}(Object(App\\\\Jobs\\\\ProcessUserImport))\\n#16 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Bus\\/Dispatcher.php(130): Illuminate\\\\Pipeline\\\\Pipeline->then(Object(Closure))\\n#17 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Queue\\/CallQueuedHandler.php(126): Illuminate\\\\Bus\\\\Dispatcher->dispatchNow(Object(App\\\\Jobs\\\\ProcessUserImport), false)\\n#18 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(170): Illuminate\\\\Queue\\\\CallQueuedHandler->{closure:Illuminate\\\\Queue\\\\CallQueuedHandler::dispatchThroughMiddleware():121}(Object(App\\\\Jobs\\\\ProcessUserImport))\\n#19 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(127): Illuminate\\\\Pipeline\\\\Pipeline->{closure:Illuminate\\\\Pipeline\\\\Pipeline::prepareDestination():168}(Object(App\\\\Jobs\\\\ProcessUserImport))\\n#20 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Queue\\/CallQueuedHandler.php(121): Illuminate\\\\Pipeline\\\\Pipeline->then(Object(Closure))\\n#21 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Queue\\/CallQueuedHandler.php(69): Illuminate\\\\Queue\\\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\\\Queue\\\\Jobs\\\\SyncJob), Object(App\\\\Jobs\\\\ProcessUserImport))\\n#22 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Queue\\/Jobs\\/Job.php(102): Illuminate\\\\Queue\\\\CallQueuedHandler->call(Object(Illuminate\\\\Queue\\\\Jobs\\\\SyncJob), Array)\\n#23 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Queue\\/SyncQueue.php(76): Illuminate\\\\Queue\\\\Jobs\\\\Job->fire()\\n#24 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Queue\\/SyncQueue.php(56): Illuminate\\\\Queue\\\\SyncQueue->executeJob(Object(App\\\\Jobs\\\\ProcessUserImport), \'\', \'default\')\\n#25 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Bus\\/Dispatcher.php(244): Illuminate\\\\Queue\\\\SyncQueue->push(Object(App\\\\Jobs\\\\ProcessUserImport), \'\', \'default\')\\n#26 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Bus\\/Dispatcher.php(228): Illuminate\\\\Bus\\\\Dispatcher->pushCommandToQueue(Object(Illuminate\\\\Queue\\\\SyncQueue), Object(App\\\\Jobs\\\\ProcessUserImport))\\n#27 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Bus\\/Dispatcher.php(77): Illuminate\\\\Bus\\\\Dispatcher->dispatchToQueue(Object(App\\\\Jobs\\\\ProcessUserImport))\\n#28 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Bus\\/PendingDispatch.php(222): Illuminate\\\\Bus\\\\Dispatcher->dispatch(Object(App\\\\Jobs\\\\ProcessUserImport))\\n#29 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Controllers\\/AdminEmployeeImportController.php(103): Illuminate\\\\Foundation\\\\Bus\\\\PendingDispatch->__destruct()\\n#30 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Controller.php(54): App\\\\Http\\\\Controllers\\\\AdminEmployeeImportController->start(Object(Illuminate\\\\Http\\\\Request))\\n#31 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/ControllerDispatcher.php(44): Illuminate\\\\Routing\\\\Controller->callAction(\'start\', Array)\\n#32 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Route.php(266): Illuminate\\\\Routing\\\\ControllerDispatcher->dispatch(Object(Illuminate\\\\Routing\\\\Route), Object(App\\\\Http\\\\Controllers\\\\AdminEmployeeImportController), \'start\')\\n#33 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Route.php(212): Illuminate\\\\Routing\\\\Route->runController()\\n#34 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Router.php(808): Illuminate\\\\Routing\\\\Route->run()\\n#35 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(170): Illuminate\\\\Routing\\\\Router->{closure:Illuminate\\\\Routing\\\\Router::runRouteWithinStack():807}(Object(Illuminate\\\\Http\\\\Request))\\n#36 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Middleware\\/CheckInitialPayment.php(27): Illuminate\\\\Pipeline\\\\Pipeline->{closure:Illuminate\\\\Pipeline\\\\Pipeline::prepareDestination():168}(Object(Illuminate\\\\Http\\\\Request))\\n#37 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): App\\\\Http\\\\Middleware\\\\CheckInitialPayment->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#38 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Middleware\\/SetOrganization.php(135): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#39 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): App\\\\Http\\\\Middleware\\\\SetOrganization->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#40 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Middleware\\/Auth.php(61): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#41 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): App\\\\Http\\\\Middleware\\\\Auth->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure), \'admin\')\\n#42 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Middleware\\/CookieConsentMiddleware.php(24): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#43 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): App\\\\Http\\\\Middleware\\\\CookieConsentMiddleware->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#44 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Middleware\\/SetLocale.php(44): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#45 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): App\\\\Http\\\\Middleware\\\\SetLocale->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#46 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Middleware\\/SubstituteBindings.php(51): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#47 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Routing\\\\Middleware\\\\SubstituteBindings->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#48 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/VerifyCsrfToken.php(88): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#49 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\VerifyCsrfToken->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#50 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/View\\/Middleware\\/ShareErrorsFromSession.php(49): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#51 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\View\\\\Middleware\\\\ShareErrorsFromSession->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#52 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Session\\/Middleware\\/StartSession.php(121): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#53 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Session\\/Middleware\\/StartSession.php(64): Illuminate\\\\Session\\\\Middleware\\\\StartSession->handleStatefulRequest(Object(Illuminate\\\\Http\\\\Request), Object(Illuminate\\\\Session\\\\EncryptedStore), Object(Closure))\\n#54 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Session\\\\Middleware\\\\StartSession->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#55 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Cookie\\/Middleware\\/AddQueuedCookiesToResponse.php(37): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#56 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Cookie\\\\Middleware\\\\AddQueuedCookiesToResponse->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#57 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Cookie\\/Middleware\\/EncryptCookies.php(75): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#58 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Cookie\\\\Middleware\\\\EncryptCookies->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#59 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(127): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#60 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Router.php(807): Illuminate\\\\Pipeline\\\\Pipeline->then(Object(Closure))\\n#61 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Router.php(786): Illuminate\\\\Routing\\\\Router->runRouteWithinStack(Object(Illuminate\\\\Routing\\\\Route), Object(Illuminate\\\\Http\\\\Request))\\n#62 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Router.php(750): Illuminate\\\\Routing\\\\Router->runRoute(Object(Illuminate\\\\Http\\\\Request), Object(Illuminate\\\\Routing\\\\Route))\\n#63 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Router.php(739): Illuminate\\\\Routing\\\\Router->dispatchToRoute(Object(Illuminate\\\\Http\\\\Request))\\n#64 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Kernel.php(201): Illuminate\\\\Routing\\\\Router->dispatch(Object(Illuminate\\\\Http\\\\Request))\\n#65 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(170): Illuminate\\\\Foundation\\\\Http\\\\Kernel->{closure:Illuminate\\\\Foundation\\\\Http\\\\Kernel::dispatchToRouter():198}(Object(Illuminate\\\\Http\\\\Request))\\n#66 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Middleware\\/ContentSecurityPolicy.php(32): Illuminate\\\\Pipeline\\\\Pipeline->{closure:Illuminate\\\\Pipeline\\\\Pipeline::prepareDestination():168}(Object(Illuminate\\\\Http\\\\Request))\\n#67 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): App\\\\Http\\\\Middleware\\\\ContentSecurityPolicy->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#68 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/TransformsRequest.php(21): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#69 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/ConvertEmptyStringsToNull.php(31): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\TransformsRequest->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#70 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\ConvertEmptyStringsToNull->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#71 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/TransformsRequest.php(21): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#72 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/TrimStrings.php(51): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\TransformsRequest->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#73 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\TrimStrings->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#74 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Http\\/Middleware\\/ValidatePostSize.php(27): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#75 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Http\\\\Middleware\\\\ValidatePostSize->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#76 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/PreventRequestsDuringMaintenance.php(110): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#77 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\PreventRequestsDuringMaintenance->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#78 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Http\\/Middleware\\/HandleCors.php(49): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#79 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Http\\\\Middleware\\\\HandleCors->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#80 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Http\\/Middleware\\/TrustProxies.php(58): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#81 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Http\\\\Middleware\\\\TrustProxies->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#82 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(127): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#83 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Kernel.php(176): Illuminate\\\\Pipeline\\\\Pipeline->then(Object(Closure))\\n#84 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Kernel.php(145): Illuminate\\\\Foundation\\\\Http\\\\Kernel->sendRequestThroughRouter(Object(Illuminate\\\\Http\\\\Request))\\n#85 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/public\\/index.php(50): Illuminate\\\\Foundation\\\\Http\\\\Kernel->handle(Object(Illuminate\\\\Http\\\\Request))\\n#86 {main}\"}',NULL,NULL,1,'2025-10-09 11:14:43',NULL,'2025-10-09 11:14:43','2025-10-09 11:14:43'),
(2,1,1,'import_20251009131822.json','employee_import.xlsx',NULL,3,3,0,3,0,'completed',NULL,NULL,NULL,1,'2025-10-09 11:18:22','2025-10-09 11:18:22','2025-10-09 11:18:22','2025-10-09 11:18:22');
/*!40000 ALTER TABLE `user_import_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_import_results`
--

DROP TABLE IF EXISTS `user_import_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_import_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_job_id` bigint(20) unsigned NOT NULL,
  `row_number` int(11) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `department_name` varchar(255) DEFAULT NULL,
  `status` enum('success','failed','skipped','updated') NOT NULL,
  `action_taken` enum('created','updated') DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_result_import_job` (`import_job_id`),
  KEY `idx_result_user` (`user_id`),
  KEY `idx_result_email` (`email`),
  KEY `idx_result_status` (`status`),
  CONSTRAINT `fk_result_import_job` FOREIGN KEY (`import_job_id`) REFERENCES `user_import_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_result_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_import_results`
--

LOCK TABLES `user_import_results` WRITE;
/*!40000 ALTER TABLE `user_import_results` DISABLE KEYS */;
INSERT INTO `user_import_results` VALUES
(1,2,1,NULL,'gipszj@nwbusiness.hu','Gipsz Jakab',NULL,'failed',NULL,'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'wage\' in \'field list\' (Connection: mysql, SQL: insert into `user_wages` (`user_id`, `organization_id`, `wage`, `currency`, `created_at`) values (82, 1, 350000, HUF, 2025-10-09 13:18:22))','2025-10-09 11:18:22'),
(2,2,2,NULL,'andreab@nwbusiness.hu','Andrea Bocelli',NULL,'failed',NULL,'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'wage\' in \'field list\' (Connection: mysql, SQL: insert into `user_wages` (`user_id`, `organization_id`, `wage`, `currency`, `created_at`) values (83, 1, 600000, HUF, 2025-10-09 13:18:22))','2025-10-09 11:18:22'),
(3,2,3,NULL,'margareta@nwbusiness.hu','Margaret Island',NULL,'failed',NULL,'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'wage\' in \'field list\' (Connection: mysql, SQL: insert into `user_wages` (`user_id`, `organization_id`, `wage`, `currency`, `created_at`) values (84, 1, 700000, HUF, 2025-10-09 13:18:22))','2025-10-09 11:18:22');
/*!40000 ALTER TABLE `user_import_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_login`
--

DROP TABLE IF EXISTS `user_login`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_login` (
  `user_id` bigint(20) unsigned NOT NULL,
  `logged_in_at` datetime NOT NULL,
  `token` varchar(255) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  KEY `user_login_fk1_idx` (`user_id`),
  CONSTRAINT `user_login_fk1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_login`
--

LOCK TABLES `user_login` WRITE;
/*!40000 ALTER TABLE `user_login` DISABLE KEYS */;
INSERT INTO `user_login` VALUES
(1,'2022-08-26 08:09:48','YqjRtySEUUfbtOVJW9sTpE6QHUZpglkGV4xB9WYH',NULL,NULL),
(1,'2022-08-26 10:00:39','YqjRtySEUUfbtOVJW9sTpE6QHUZpglkGV4xB9WYH',NULL,NULL),
(1,'2022-08-26 10:09:31','YqjRtySEUUfbtOVJW9sTpE6QHUZpglkGV4xB9WYH',NULL,NULL),
(1,'2022-08-31 15:10:05','yDwxtRhM0mCFOVjqLwzP0ntDDnRbkFPL3LjAzfuf',NULL,NULL),
(1,'2022-08-31 15:17:45','exnLq0g1oIprZynYTgMWDj8SIELx6ylncMLVj0mW',NULL,NULL),
(1,'2022-08-31 15:21:32','yDwxtRhM0mCFOVjqLwzP0ntDDnRbkFPL3LjAzfuf',NULL,NULL),
(1,'2022-09-01 11:28:24','yY0CdeaOqzp3GrpUqJZfmpVEeFOcU83zvxpMaj9b',NULL,NULL),
(1,'2022-09-08 10:34:36','hYw3PloFqBfhp62xGGoOp4uMGZr3orwTtdurL7Nn',NULL,NULL),
(1,'2022-09-09 09:06:14','oRTD6sNGIIE9JsdzirPtIGZhG4PHGm7oSRKf7QAI',NULL,NULL),
(1,'2022-09-09 12:46:42','85LwOcGvAbZ391SWXmlqV3kSYqr750P933LvRxaS',NULL,NULL),
(1,'2022-09-09 14:49:59','7dU50Ww7KfmSEpOaqD1UdC65HUavaGgTyKE7Bf0C',NULL,NULL),
(1,'2022-09-09 14:53:58','85LwOcGvAbZ391SWXmlqV3kSYqr750P933LvRxaS',NULL,NULL),
(1,'2022-09-09 15:03:38','7dU50Ww7KfmSEpOaqD1UdC65HUavaGgTyKE7Bf0C',NULL,NULL),
(1,'2022-09-09 15:11:57','85LwOcGvAbZ391SWXmlqV3kSYqr750P933LvRxaS',NULL,NULL),
(1,'2022-09-12 07:45:36','SjQZRgX2vbB7lqMShSlysS33DbUaQPXofu09Esom',NULL,NULL),
(1,'2022-09-12 13:20:54','NwYdPgH0WZG1KCLl6DSztpAHleODEorIexsaK7Fz',NULL,NULL),
(1,'2022-09-12 13:25:39','gQVLUbYxTTOdViWY0FC5x4tOEvtMseyS2rRPUsw7',NULL,NULL),
(1,'2022-09-12 16:10:49','gQVLUbYxTTOdViWY0FC5x4tOEvtMseyS2rRPUsw7',NULL,NULL),
(1,'2022-09-13 08:09:46','VyUdCwBkrsW7U5GqV17NhoxGSs6mdph1Q9S8qjvA',NULL,NULL),
(1,'2022-09-13 13:52:01','TKa9IbIRl6dR8CQgbrNf1Ct73giOjeLdI7q9J5vv',NULL,NULL),
(1,'2022-09-14 15:04:56','jlIEHiLxpEuCb8foBgXNtPSgXdpmfeDh2mmR2WdY',NULL,NULL),
(1,'2022-09-15 08:08:43','iQRpHvCr6HOPKtbOCVwH5o4EBY0PKljggDaoNlvP',NULL,NULL),
(1,'2022-09-15 12:57:58','0e20mBf7YTy4d8OMZiPfuFcPqaWfXYsY3B6SQtnY',NULL,NULL),
(1,'2022-09-16 08:47:53','4qdjCLi6n1CGX0jTJ1BhBB6jZW8J6ko2GBsIVlnn',NULL,NULL),
(1,'2022-09-16 16:06:24','2XZBrwLioLg12hzWCcnEtt66PJgFH2gm4S0hLAyw',NULL,NULL),
(1,'2022-09-19 08:00:07','pQUd9utmtVDKdDPf5o5XYaZpwbJHPZV3pD5JfOuW',NULL,NULL),
(1,'2022-09-19 08:28:51','iWO6VtDyk8fW2tZ43Q3V4sT4YJzqYXMgXMphOJxJ',NULL,NULL),
(1,'2022-09-19 09:24:46','pQUd9utmtVDKdDPf5o5XYaZpwbJHPZV3pD5JfOuW',NULL,NULL),
(1,'2022-09-19 17:58:19','j0ylnk8rVyitpembi4kjqgfXck8rgCz3gA222QCk',NULL,NULL),
(1,'2022-09-20 08:20:35','LohY3XUOHLPAJwEYL4GLl6nFkBqNvGhqzYVhNpOz',NULL,NULL),
(1,'2022-09-22 14:40:53','99h8jeel1KjpAy1ApBqTwzczJJGbPWTPV7rsi5yD',NULL,NULL),
(1,'2022-09-23 09:57:38','Jzey8SpY4RkQz1vlL3Nuyv3qHYKywgTAh5pm7OrJ',NULL,NULL),
(1,'2022-09-23 12:46:57','2csvQPpdPe22LCJnA011jBP7JHBN1RvKypnZHAWE',NULL,NULL),
(1,'2022-09-23 14:34:46','3PjtxeHww82mX33GKDhDje3eXGi347MbFYcLo8B7',NULL,NULL),
(1,'2022-09-23 14:54:58','2csvQPpdPe22LCJnA011jBP7JHBN1RvKypnZHAWE',NULL,NULL),
(1,'2022-09-23 15:01:17','3PjtxeHww82mX33GKDhDje3eXGi347MbFYcLo8B7',NULL,NULL),
(1,'2022-09-25 19:43:33','9QrDyV1OISBXWZvSqGDQoWGWeLTfb85b9jySgwGr',NULL,NULL),
(1,'2022-09-26 08:31:18','LAmKU9V4nqqRQo7f5QawGd7oUX4FhDQuLt5dg933',NULL,NULL),
(1,'2022-09-26 10:19:01','LAmKU9V4nqqRQo7f5QawGd7oUX4FhDQuLt5dg933',NULL,NULL),
(1,'2022-09-26 11:04:32','LAmKU9V4nqqRQo7f5QawGd7oUX4FhDQuLt5dg933',NULL,NULL),
(1,'2022-09-26 12:28:09','LAmKU9V4nqqRQo7f5QawGd7oUX4FhDQuLt5dg933',NULL,NULL),
(1,'2022-09-27 09:13:51','6B7ZYArQEf0bl9bPA8ZCruaRsBGgFodS8xOkrBKB',NULL,NULL),
(1,'2022-09-27 19:09:25','lw9prdXrSJAuPsFuwv6xkTqBBL9BUorPv1qgOHej',NULL,NULL),
(1,'2022-09-29 08:41:45','6CMMkPdHTsUYzDOouzJXYCn16BYfwrK2n5xpCe7u',NULL,NULL),
(1,'2022-09-29 11:01:58','MTQtueFEVVd0LypQ67NniZNCLHKWg6925qU0Hk7Q',NULL,NULL),
(1,'2022-09-30 09:28:41','sG8qHI1LnXYANCAE0tmAPoBjGkEE1nebBFmuOy41',NULL,NULL),
(1,'2022-10-03 08:01:11','s7rvKpV4H6rn5uSr678pb7OkfBQnhmksHucbMxFF',NULL,NULL),
(1,'2022-10-03 13:35:20','dC41C9H8PGr10igLs1EsbTx3NKBl5SpYoELcVvuh',NULL,NULL),
(1,'2022-10-03 15:31:21','dC41C9H8PGr10igLs1EsbTx3NKBl5SpYoELcVvuh',NULL,NULL),
(1,'2022-10-04 08:48:35','P94TBYTV4hFqG5MXisVIlQ37HZfQJgEuqvbwpkm7',NULL,NULL),
(1,'2022-10-04 11:58:13','15eKOClKJBNl83J5f9UNMsVK7j8h9LVKPjpuk9K0',NULL,NULL),
(1,'2022-10-04 13:59:14','xjQHH3xLwNGXpmsrOoyhIehH1yIk5v9aXrmjUJ7u',NULL,NULL),
(1,'2022-10-05 16:31:47','cKizQQNaQegnww42ZoEpMigmSYYtH6egwJfaEK4U',NULL,NULL),
(1,'2022-10-06 08:36:25','nUFL68ovQpi2M54UjLSovCtHe762HJC0uSo5UOmt',NULL,NULL),
(1,'2022-10-06 11:36:34','oDKJVeFVFCKYmdabxeq3A2BQUa8LtoL74DxAxn3s',NULL,NULL),
(1,'2022-10-06 13:41:40','GdzHeB4PJsnV1L4AxnRcORNoECIAQoA5M1z2LfON',NULL,NULL),
(1,'2022-10-06 15:31:43','grBGTSHvoYdUXcUiO07zT4cUpjUKYFPGudb3Jnkb',NULL,NULL),
(1,'2022-10-06 15:37:03','GdzHeB4PJsnV1L4AxnRcORNoECIAQoA5M1z2LfON',NULL,NULL),
(1,'2022-10-06 20:09:28','363I4jBYTqjgVlojZmjGsWi5FDufsp0o7Ad4wJzo',NULL,NULL),
(1,'2022-10-07 13:01:12','ifM6uqnDVfGkObppW5yYoH099KozXP517Of35Miu',NULL,NULL),
(1,'2022-10-10 08:51:33','6pHB0kGmeyqPIfhpSyR0nsSwNXfx1frZZkh5kkw0',NULL,NULL),
(1,'2022-10-13 08:16:00','E9FcyAgrD6Dzkl5ESIoIsAatMHq6CWTVXgPe2xY5',NULL,NULL),
(1,'2022-10-19 15:02:22','Pr6MC42rKWfexRSd4GJNpGMqY8fBcH3h4STmun5F',NULL,NULL),
(1,'2022-10-26 17:24:42','aCLj4HwSkqIlrHCNNIE74ZT7YzgoY4cSs6uSRwnY',NULL,NULL),
(1,'2022-11-21 08:06:29','AA2McONji1RL0YH7QZdVKaYyHSCwE2WE2v0R0Qby',NULL,NULL),
(1,'2022-11-22 15:27:05','W6P89jIFyCLDiQNEYQGfMfCWnk0xsIGLhRYHiPnM',NULL,NULL),
(1,'2022-11-29 10:11:06','8fmHjhktdJa3kB1Ry9C4k8djpKAbr2jjTxSAOfkc',NULL,NULL),
(1,'2022-11-30 17:15:37','aSVBAqxceyZx3VM0NFoGKghwJKrX8UaBKn9nI5mP',NULL,NULL),
(1,'2022-12-05 16:29:25','aQQQXIsW6CbOuslfJL9qneeUcrOCVPsVA2duPk1H',NULL,NULL),
(1,'2022-12-05 16:38:57','XvjOBZbGqnQBvlmgWaIe2LyHx38tAsdNXTzFRbhj',NULL,NULL),
(1,'2022-12-08 08:12:14','3SRN59IQGzqdv9ElX3O4yYIoPMbafs1YSUMBUvsx',NULL,NULL),
(1,'2022-12-09 07:49:09','aTO0yl8skSTUbpSa9artkEG0OYcggkVUWVkWLjE9',NULL,NULL),
(1,'2022-12-09 13:52:26','38DQGggCW05ItrsZNTS3vpaRHRjNT7ynPhrvoxIp',NULL,NULL),
(1,'2022-12-09 13:52:39','GHy96CqYl9yxgUMmK004e7WvBT5E60U7F4Gi7KRq',NULL,NULL),
(1,'2022-12-12 09:01:10','Lo5KFZEUtjzkpxidcfV1KAjutmV6tnVpuZSAf8fY',NULL,NULL),
(1,'2022-12-12 09:08:33','rzMKn0ftqa7dyovAvSXMZ1Ep0ioy4tefeiZevhMI',NULL,NULL),
(1,'2022-12-13 13:39:06','btoDy19i3OJMzLBjtIn0PRodB9PjA4mjiXFF5MF6',NULL,NULL),
(1,'2022-12-14 08:39:39','YriN5so9y3Q7jC71nA0zOYIPs5GNYKrlK7f7HZpH',NULL,NULL),
(1,'2022-12-14 15:36:58','iy3Os5hFjsWSLt2P6nYCiRyal2vawByGg2Hx4PnW',NULL,NULL),
(1,'2022-12-15 14:24:00','SdWqSFB4WUtrblI0uY5rLaD71yHNeFev81w4opY7',NULL,NULL),
(1,'2022-12-16 09:21:22','2fMjj2haXsD9zJgsFLo957F4uEMdYcJ5V0PYKvbo',NULL,NULL),
(1,'2022-12-19 17:09:47','ebtMZpPUwAL3uJAr8w7KLdXaXxks6CxaXETXg1Ng',NULL,NULL),
(1,'2022-12-20 09:30:15','sMZJwLvBigZOyNahxtv7EmY2p2Q9bhgf5GOPy63e',NULL,NULL),
(1,'2022-12-20 19:15:24','LXBP4uUXaschvxrkySwdGxZ4MmzPTBPEQWMfv0sX',NULL,NULL),
(1,'2022-12-21 09:51:18','ADq6eolkQIvyF1sxo2ZT0u6sFuWjau2NCkjuJUPM',NULL,NULL),
(1,'2022-12-22 08:14:15','UO5k1kdAQevOXq2DxnCoWm0MDaGjCiPMXrH2ERDL',NULL,NULL),
(1,'2022-12-22 12:30:36','pjm1sCJLlTgrs3fwaka2Y4ktzSBQmQvqOI8Bv8ml',NULL,NULL),
(1,'2022-12-27 16:20:51','a7EstgeTk7ua1D4KBSQzZR8R3y5kZhJMaIcBRCom',NULL,NULL),
(1,'2023-01-03 15:52:16','vB90X1ezslURuBPuZHQeUPnGM3MCeFkiVzCAMPtT',NULL,NULL),
(1,'2023-01-04 08:57:12','vLBBKd3wtNpG2eI54Pkacw95Pw5WZDs8WUPCcDT8',NULL,NULL),
(1,'2023-01-06 09:38:09','3QwA7cUTnXHzZr9PJm2Q5Yks7nDvkWSZ2uJZ92CP',NULL,NULL),
(1,'2023-01-09 08:20:30','bIuTo0z5CvAW2H9QCx6fP5SGhZTjhS4m2Gj5VWku',NULL,NULL),
(1,'2023-01-11 08:14:20','MfXvQuNqzWFOiolIERadMm8MXUmtQUHmz3b3RRDw',NULL,NULL),
(1,'2023-03-01 10:26:42','U6lKC0RClKHY8aTryIBp0npuTOIEqMmMSeoMLFal',NULL,NULL),
(1,'2023-03-20 11:23:57','wsPmGw9UZ0HIszaPpsbEVV4R3QWZljRlfTQ4pTjw',NULL,NULL),
(1,'2023-03-20 13:46:02','5SJCHOcGMP8uxnCdFGssHaiSNf6QRsS4MQOJaMAs',NULL,NULL),
(1,'2023-03-20 14:02:55','wsPmGw9UZ0HIszaPpsbEVV4R3QWZljRlfTQ4pTjw',NULL,NULL),
(1,'2023-03-21 12:43:17','xjpmCd4Pq4Z9M6CJVfrwG3L40jqYwjoOcTBaj0CB',NULL,NULL),
(1,'2023-03-22 13:27:31','3WWQUozp2SIFvDMZ8CozgJENBbqTU0MMseG39lWR',NULL,NULL),
(1,'2023-03-23 11:34:13','CVOqz0Jp7SEBADNqZZyyJea6Bf8h3yatEU3CXgpG',NULL,NULL),
(1,'2023-03-27 09:32:34','bS65Z2zaaqqpHFUvHBSb0lSLWHVZuvllNINw6l0T',NULL,NULL),
(1,'2023-03-28 16:27:19','xCz0Te9yyQwpJcVmotPXabyuvCXdyRE7A9hrUbpR',NULL,NULL),
(1,'2023-03-29 14:40:44','0WKIfo7lRo8vJefw0f5pawW6XwVHJMSiYOnMG0r0',NULL,NULL),
(1,'2023-03-30 07:45:00','B2zdo4KuNSIMEiJb3mUfbmNQ6sTASVa39cozHVdp',NULL,NULL),
(1,'2023-03-31 08:02:14','qrJds3V9AbCpt0JEretmLKkDgoSJRY3uvEDvzg0m',NULL,NULL),
(1,'2023-03-31 14:53:39','xVPqQdD7uf9iqg06eCYUMYtoRJYlg4yq085ZeCOc',NULL,NULL),
(1,'2023-04-03 08:06:14','xfYB9qbShXjBnZT6qLMZrddmiMeNdeVNhxfNf7s3',NULL,NULL),
(1,'2023-04-03 11:33:34','5rcdCarlwxb38eH6ESqHBCNS1MDeeVriOf3Sb87M',NULL,NULL),
(1,'2023-04-03 12:20:55','xfYB9qbShXjBnZT6qLMZrddmiMeNdeVNhxfNf7s3',NULL,NULL),
(1,'2023-04-04 08:14:51','4XRgChACPiZlHmiY3UheGvAjrEC0S1rvA2KBO2ro',NULL,NULL),
(1,'2023-04-26 20:54:46','USs7SyzvbBFWUc8jqc1cEusntjsuqj0SeUpp1NcO',NULL,NULL),
(1,'2023-05-02 08:53:30','TSyjmkoTf02B75FH5ze8qWehqwXrZ4QGcSN5bALN',NULL,NULL),
(1,'2023-06-20 15:17:45','UB0vo9jnerluBSYGpEzrndhKLSZt2odchpQYZj0b',NULL,NULL),
(1,'2023-06-20 17:25:38','68gi8Rf9VCGs4gWrXLf5yEcgyV4g7o5SLFhAXPSI',NULL,NULL),
(1,'2023-06-21 09:06:28','fof82sZSBhjLiJ2UsHQ8VEZgRlq04rcRUx63g9WI',NULL,NULL),
(1,'2023-06-22 10:01:27','CQQh2b7e6WD0l07RInDqpzsqaEy1pCs3e9Kyh4xV',NULL,NULL),
(1,'2023-06-23 12:58:06','L4VZG0lGxeQcHMkZRmc026sVb59R9Nvm5dlZ0lKp',NULL,NULL),
(1,'2023-06-26 16:47:29','zZQBRQTs2aXVQInu7A3mKxDDucsr5BiNCrbzlPgH',NULL,NULL),
(1,'2023-06-27 14:25:25','7G7V4VL2SUBoGowPt5PgICHOfiHgcYtc4cnAM6vc',NULL,NULL),
(1,'2023-06-29 07:33:00','7ig3o3d5s4LlneZh1k0zwWvL8qfREiF4sQaFOmOv',NULL,NULL),
(1,'2023-06-29 11:05:18','tqdCnO65mYzAfGixHnSIGTZiEKqry20Ru0eUWyCx',NULL,NULL),
(1,'2023-06-29 19:19:46','lyHCn4JpKAqf30iEqL1clYIKBVzOVp3fvgX1Y0Km',NULL,NULL),
(1,'2023-07-02 15:15:58','FyRxqGVGsEf2tnSF2FXNs1tMwCYBr8fTnoVruYJs',NULL,NULL),
(1,'2023-07-03 11:36:53','NsGklQXSY2Hvqr6TpAjd9qSu2UjzKyU5NeUi2wGu',NULL,NULL),
(1,'2023-07-03 13:56:44','X1B0wlUzRD32oE1HIMzneCnQnDy6XDgJfwqbUHri',NULL,NULL),
(1,'2023-07-03 17:13:55','Sxy1rjlzrONmO8SuPnY7rAr9repNxH05uFJIgp08',NULL,NULL),
(1,'2023-07-04 07:03:15','7TqDvX7OkAKyLltVNnqirFRYN0D7XAA7oRy984ms',NULL,NULL),
(1,'2023-07-04 08:44:45','eKd7VSWd0rJe6Oenh1Q3AEfjbdx57PZtzOh7p0Rz',NULL,NULL),
(1,'2023-07-04 11:23:03','vepYqcReMbW4Ckf5BSo16ZnPtMog6woO7KrrqVd5',NULL,NULL),
(1,'2023-07-04 15:59:35','v7OqLUXNRyvf3BdPbJ9DvtNNbO48l9g4bmsXNHLL',NULL,NULL),
(1,'2023-07-05 07:44:55','2WFlD0FzV2rUm9rLtl3n1WeZnoFlcIiG4JLONcW1',NULL,NULL),
(1,'2023-07-05 07:46:01','wQsBEiyUrWgIREHezJHtxivnwX87wfsOXtj2SNpb',NULL,NULL),
(1,'2023-07-05 07:50:06','2WFlD0FzV2rUm9rLtl3n1WeZnoFlcIiG4JLONcW1',NULL,NULL),
(1,'2023-08-02 08:18:14','iUwUh7kxaj7oG1sjScX8jKywttxvahdVE1z4DMtz',NULL,NULL),
(1,'2023-08-14 22:26:23','2OYllan8kVfUJIIOzPFOEqetnDZBRuNSA75KPgRf',NULL,NULL),
(1,'2023-09-12 07:40:56','E6sHOYlF0phbfGmplFTm9gtOZHhX1LhJlcmmRhQu',NULL,NULL),
(1,'2023-09-12 10:53:30','75Qh6cisSKdJshT9sryBp1VG6AIuB69YmQEneyOf',NULL,NULL),
(1,'2023-09-18 07:04:06','tEM4rXK85r0p08apvcIZPaTzLPOve8JbVfL81g8i',NULL,NULL),
(1,'2023-09-18 13:38:41','fa3hEMXJBzm7glYUMclNA5sbJVAV4kZ5m4hmQ5vX',NULL,NULL),
(1,'2023-09-19 14:57:38','BsA1dcBAoib1CrDhLjONlcHH15ULO8z4OKsFLfW3',NULL,NULL),
(1,'2023-09-21 08:55:05','fa9KDDYfrkpGfF92n1F37kGrxZUhwtjzgKBBvTKx',NULL,NULL),
(1,'2023-09-21 16:57:16','kSBTf0aAahyNBlVcmBfxKrbCs78YtIG0x9gFeIJx',NULL,NULL),
(1,'2023-09-27 07:22:07','T0KL93hsVxCS2y2YeqJeHMwM4hzwfWk6riFAI3KT',NULL,NULL),
(1,'2023-09-29 10:58:15','CMhakCeGD3tfX30klqw9NViQiy1DOMK6vvASyhG9',NULL,NULL),
(1,'2023-10-02 07:05:28','TcBsYXlOE8njbDWl7crIZOZCt3AbqPlA8BFpH6t3',NULL,NULL),
(1,'2023-10-02 14:12:34','edh851X2g8SX64Dn5Q51e14dWDAjdZqVwWH09gsX',NULL,NULL),
(1,'2023-10-03 08:35:15','zq0odUHEWh98iZngMztcUBVBYGDnbvuHDUtHdfXc',NULL,NULL),
(1,'2023-10-03 15:00:58','aGK6WFWvVklZkSWipxCoDgXLE6gJHZDzt6QZ0yD1',NULL,NULL),
(1,'2023-10-04 09:10:52','ZrriZMXzmdBHSf6kFIkFPvoJzC9KhEsX1sLeivJR',NULL,NULL),
(1,'2023-12-13 16:35:50','ckmKqtslCaRmursLFKsQBFQsx3mcqdIIs0fePNnG',NULL,NULL),
(33,'2023-12-14 07:06:18','z2hK2cBL5XolLHOMN5rjI1HIEp2wfOpDCv3Sfktk',NULL,NULL),
(33,'2023-12-15 10:39:14','3TfUoJ5in3xvkYDI1d0RMuCbLHtiP9YnfCyHLBUh',NULL,NULL),
(1,'2023-12-20 13:05:39','GQbZRwFeYE5QJxuFpYhFfyzTSqfbA4TIz5N9LpoQ',NULL,NULL),
(1,'2023-12-27 11:11:27','L9S2eMGAEnOU1GvTz2PyoU9AzbTg7ctkRbsLFCRE',NULL,NULL),
(1,'2023-12-28 13:54:33','MVY7GEL235tuxP6JgCGyMYKc2NWQnIVKMlrgzbyH',NULL,NULL),
(1,'2024-01-02 10:26:59','oLOHRlSlZ9LrIHKCrEhFT59fVcF9AxfRSgT501td',NULL,NULL),
(1,'2024-01-02 14:59:18','MZbhVZP4b3BCvvVwJBJzsJrojbBwDBHTNbEMde75',NULL,NULL),
(1,'2024-01-02 22:40:53','EL0YlPe10nUCoLjmPf0MdzjvkwRyNRX7frC0olv2',NULL,NULL),
(1,'2024-01-04 10:21:31','E3VmLmVirly2oz9sefZP7EUGPRVhH0TJqxQ9tvDr',NULL,NULL),
(1,'2024-01-04 13:27:22','vcwY9zw1sBZal0FU4awvYfofLV9gzfQOetbD0Bs8',NULL,NULL),
(33,'2024-01-04 14:18:59','vaCg3op8LF8hXNarjLpo7AX5PMuHVLZX92eiZ2vP',NULL,NULL),
(1,'2024-01-06 09:57:34','JEyx0kIOYvecMJy9Tq6ghqqoEVOlbozRyehUUlTd',NULL,NULL),
(33,'2024-01-08 07:23:23','7e97Upgi5mHNkT0Wac1XJ78cgKGx6YsXulQCtfIF',NULL,NULL),
(33,'2024-03-13 12:13:23','L2DE5s45zS7Rq4u49262Dgd1cpeL9AhtNZRkBqSj',NULL,NULL),
(1,'2024-03-19 08:33:31','F52kizOkRHtpA92HTB3RRpiAY3B3fZCpBCmDUyFh',NULL,NULL),
(1,'2024-03-19 11:00:18','fLuZZJ7v4OsT3ovojSveEt1tJXDDgqxnryELPrfm',NULL,NULL),
(33,'2024-03-19 13:24:20','VrPv3II75c1hVMQJj7DMyH9HvhmTPrAIkxJjldDi',NULL,NULL),
(1,'2024-03-25 11:55:30','AZmQtFHgUD5KtsYZ9MpCjahZfx7I434yM65hiLI2',NULL,NULL),
(1,'2024-03-27 13:27:12','QOnuvNJbH23jcYfc8a2e1EQGgoGM21TuNHRAxZi2',NULL,NULL),
(1,'2024-04-02 07:38:54','YfAxD2CfPjugxiE6BN24qGDwHde7gmKplohwkJL7',NULL,NULL),
(1,'2024-04-02 09:15:54','VHeY1vcLZngQgieH7TDalhuqXyD2EEXLOfTnPykd',NULL,NULL),
(1,'2024-04-02 13:43:53','v87KGumqsn9zkdlfdC5RfKe1v1DnZvTy8nTt4LFo',NULL,NULL),
(1,'2024-04-03 07:01:00','3FdgRd7TFiIV15BkoXUxA5BmaT6kSqd7ClHKar70',NULL,NULL),
(1,'2024-04-03 07:42:09','TBlmwEKWpHOgLsa2ndrLpKuP6ybNaVGGEeklTaBZ',NULL,NULL),
(33,'2024-04-03 08:13:16','aoxyEUZ0Qbsi7mJfpdklCh19cbw0KhbG0EoMXC37',NULL,NULL),
(33,'2024-04-03 15:54:33','Ys66Ywl8jCwBSU5Y1zMDy7LOuNVWV9pp1j67WO84',NULL,NULL),
(1,'2024-04-03 18:38:41','owl2x9F3q6y8L8SR5a5OOFbspDtiDNYLX42JkW0d',NULL,NULL),
(33,'2024-04-19 07:58:11','hKBPGGzeIUDD50vc1D6pBEg0djp5D7xgFlWYzzzb',NULL,NULL),
(1,'2025-08-18 20:45:37','tr795FB8O3eyuuTA6pD5cUgRGi7M2pnYf5qZxidZ',NULL,NULL),
(1,'2025-08-18 21:59:27','FlkZxMcMuDPktTAHpAGKlFvVpZ33Ij0FXq41pCYM',NULL,NULL),
(1,'2025-08-18 22:00:34','QWtLyLvWSErGAMqAmzHD5MstuEfOQ4hkwQVEtwTn',NULL,NULL),
(1,'2025-08-19 08:13:07','REpllgTrAnmW4HghistksEUENzczqPGIoEARls7n',NULL,NULL),
(33,'2025-08-19 10:15:17','xwZ2DHl2K0vmqXGakFbKtCreLdKordBcfxlhzf7r',NULL,NULL),
(1,'2025-08-19 10:33:24','REpllgTrAnmW4HghistksEUENzczqPGIoEARls7n',NULL,NULL),
(1,'2025-08-19 10:44:57','REpllgTrAnmW4HghistksEUENzczqPGIoEARls7n',NULL,NULL),
(1,'2025-08-19 11:10:49','REpllgTrAnmW4HghistksEUENzczqPGIoEARls7n',NULL,NULL),
(1,'2025-08-19 14:41:22','REpllgTrAnmW4HghistksEUENzczqPGIoEARls7n',NULL,NULL),
(1,'2025-08-19 14:54:48','MqviZ5ZMYgHmfWTJ8SRhuIKa77SdoG7g24S9PzEU',NULL,NULL),
(1,'2025-08-19 17:12:11','tpyAuDTZYvkVo2s86gNE6HgrRZ8NPqr5k9ONcYmf',NULL,NULL),
(1,'2025-08-19 17:12:48','ccMJp0wMG7Eynto0vhjd9EO2zOYyxUZc7A6MiZ54',NULL,NULL),
(1,'2025-08-20 11:32:21','yO25jjHcV4TeWq30pW6k6F5SiciPCZbsyuAWnUpr',NULL,NULL),
(1,'2025-08-20 13:22:10','6BL9qxSoYz5c0LnmqsRgVyRF9rh47p6Bzytc467V',NULL,NULL),
(1,'2025-08-20 17:51:56','0PNZw0uOV5cInJ5LOwsnjtsx3tWDnT6GPT8ys7Qc',NULL,NULL),
(1,'2025-08-20 18:25:50','9syVKY32o7j4A57llqH6j8W3JGUv7SH72EUzP3pf',NULL,NULL),
(33,'2025-08-21 08:03:24','B1gYQJgSjpMMMHGrZ88wudbNNyp1bf88361eV5jI',NULL,NULL),
(1,'2025-08-21 09:59:02','0ofbPTOuejuGoaGd2YML9QCHm44Rh1tAOmJZhkAr',NULL,NULL),
(1,'2025-08-21 10:29:51','kKzPc1pvW82v2DOIzLlAMpKGkwW7HkmDRTvNHQvC',NULL,NULL),
(1,'2025-08-21 12:49:51','HKBOdrAPcKTJd27dpsIbgVvaOqPKFWVgxSl3kTV2',NULL,NULL),
(1,'2025-08-21 18:08:11','BXElwD4UFhIRlkUTTHu5lck6wPhKTNK9NZZ0jJQT',NULL,NULL),
(1,'2025-08-22 11:33:29','fd1UZLVOEIxN9s3Vodw81XyDQsLe2QC1vKHOXOj2',NULL,NULL),
(1,'2025-08-22 11:33:54','GNv8DTEkrZ2BuFisoMWg490Fo9z6b2C6M7Y2RkuE',NULL,NULL),
(1,'2025-08-22 13:57:05','dh3TsMtZaL5LdK69RMQgLIHgVJxBNG2HSFnzuLyH',NULL,NULL),
(1,'2025-08-22 17:13:56','mrN3q1xrzSoM1ysSD940VgKuyVXY2nXb49MbF7Jb',NULL,NULL),
(1,'2025-08-22 22:07:08','9zp4V7gtaCoB0YtD3LIs9aXQ42pT0mihpCtvvB29',NULL,NULL),
(1,'2025-08-22 22:27:46','A0dkpBzfUzPGf418us9yRFDrRYck746DIgrwrJfQ',NULL,NULL),
(1,'2025-08-23 10:21:25','uqCB0SZzCelfNqOXHw78SQIgLIdGw6AxsF0vPQ5J',NULL,NULL),
(1,'2025-08-23 14:11:20','k3OgViogkbHEKzys4Sx6F0dqJMqfVzNVuSQk8I87',NULL,NULL),
(1,'2025-08-23 22:55:59','FG6GjOn1yzkQy7AR64KbdcW2CbPoX0nfNlDtITuo',NULL,NULL),
(1,'2025-08-23 23:34:45','9xgpjerov9kBGRxPt0g92UISldTq41qGuDAp9Gxu',NULL,NULL),
(1,'2025-08-23 23:53:57','JLIVeMYMVO6Yiq4XGCDoOo8IE2hqLGZv5aCkdkII','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-23 23:54:14','xvsZFbWa7hqmQUykfGb2TX26yUY5LvkWFxLVmQUm','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-24 09:03:43','1qbkWPWQ6yUTEUeQOs3TgAWcdKACGRKHQ7qamRJS','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-24 09:25:05','UEcCXF1kqfNwvY9PhdgSwV0Wldytu4UW519wPSuh','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-24 09:40:08','Z0PsVrvzaOri0iCEDuo67B5PeqUcYTUZzVB1N31L','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(55,'2025-08-24 11:26:52','OBI1ZOUgh960bsBXFcj8KphFFTRLEn3eD8NUoi8m',NULL,NULL),
(55,'2025-08-24 21:46:32','rCpEU85CboKQPskiJtLGjaz9cl6dA5zfcKxwur8R',NULL,NULL),
(1,'2025-08-24 21:46:46','QdNEafXiPFhjhIzo9yyBcIrTsSONVqQFXsYtCqSJ','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-24 23:29:45','b8unR6zlQSf0fCmsv2G9i5ZohrJgk1JNeYfkZqLa','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-24 23:34:59','cTL7BFZid4SlpSf6tfAS9YmQn83ACM5w9vsqHQHT','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-24 23:35:06','GvX9dCKe1y6Z3MRmHhw2UNDz9K0MzexoIgSHvw3e','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-24 23:35:17','n0FYmjv1qqU7wKpKaOVfn1QQWvwtlbvYj3BHuTiF','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-25 16:03:43','J7T0pKwjow7AjXVLQaVFR1vIxud7E7epYHDfNJtH','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-25 22:03:04','DXYw1FrpjguXl6Du64zsaQes3uHtBdLR1sk8vcDW','85.66.68.150','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36'),
(33,'2025-08-25 22:03:47','PpBPlV425bwx3ixdaa5uVPJvopGNNLBq4xgbfqJq','2001:4c4e:184f:5500:90f5:6fcb:204c:7c0c','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-25 22:09:55','inGvksqS1FK5mthNoVZU7hWZIbZC23KAnUN0pwhM','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-25 22:58:15','fJQuZ1arS1P8jrbnj4F97LBbK4LBn07NHd1aWVm8','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-25 23:03:36','W6y4diKDrMXPiJD6A0UTFk8F97xODdHNkPrCc65c','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-26 08:52:15','XauCUNmwtGp7Ubh0r9Z7dCo7FhbhFVC8ZCm4bwuh','91.120.123.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-26 13:37:02','K7Ff9Tas8K6aYphbC55VGpPNVMBmPahGR7VIHutk','91.120.123.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-26 14:01:27','leCBfFpouSPymtvu7vAxIO2B4J2I8vDDTopH2MC0','91.120.123.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-27 09:59:03','W3OTuDUrdiJiOdwpWcKDbADXiOAtvJyXuAiwA6oa','91.120.123.159','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-29 10:45:01','RfXKd7yKiW0fDAdwia2e065OTTPMyPQjiSxadhcy','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(43,'2025-08-29 12:47:49','dSF5TApUSG6N032cMa12qIqkOn3JIxiM7glV1cxl','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(57,'2025-08-29 14:31:44','B3rvxgkrI0xjFtgMBLZAQCeehsscfX2sV5eEgeiR','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(43,'2025-08-29 15:39:42','aGvbnID1H247FW1v7avOAr61plDXpGML3G3vzt5M','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(43,'2025-08-29 15:39:52','j91MGP2Veieeqk1XGc94yTay1ss3Y3sq9DchYqNP','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(57,'2025-08-29 15:40:27','i0kglfNgPu0RslycSts3ENN7jNSoW1XwYRsju2k6','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-30 14:40:29','qdsfYJ6Top1Yxgv1kZ9dsxzJAkrFE4xv9O0WIoFJ','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(57,'2025-08-30 17:07:38','q1zbPaoS8WbClpu523QH7eFFR58PkX7W6qUeKZce','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(57,'2025-08-30 23:57:05','kZphhziHloMfsQZpmdRy0ogQzEaKiYFexnm0iwAa','2001:4c4e:184b:3400:963c:ebde:af69:e21d','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36'),
(1,'2025-08-31 09:18:51','YNrwOv1WtHeLVZkwhYI5U7JDKJQFuiD9xeqliPWh','2001:4c4e:1845:9a00:d27:d49a:4cd:ae4a','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(43,'2025-08-31 09:21:09','CBL9rc7bpdlhYRhb67syCW7BOFuyqUIOxM2yZ84N','2001:4c4e:1845:9a00:d27:d49a:4cd:ae4a','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-31 11:49:32','rekYQTxBO80jGlWoinAVu4deq8zdER4r4TD7CMHd','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(43,'2025-08-31 19:59:21','YvJC2QwkNb8DuEXJubSVzgwwstsJtWxJZH7qJASy','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(57,'2025-08-31 21:32:04','bnJKySwxI5s8tXnutY1pkKqYLFG6X3GkSHyEEHH1','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-08-31 21:45:40','nKsrkd6M3b7H4Rb5NLdXmTsFIv1Mtz7g2A0IzHU1','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-01 09:13:59','bQswWvDsYMrPjuxOI2l6nAznagp7sZJpwoshWgxy','188.6.35.55','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-01 14:28:29','9wsR5qbFHfO7UiFmilvWBgj4lpm7xA2QkIQaAFsK','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(57,'2025-09-01 19:23:17','X14AY44XLWVa5WvlwjabZrNu1H62f0sqzZi0LQaq','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(43,'2025-09-01 20:42:13','nr59PsrS74UG2uYsmSZTSvkguoP0kpYDZxqakK8R','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-02 16:19:35','NBfqOZURMUr4BP4eegjRuf2qVpcs0rVRIEJqgOrr','85.66.67.75','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-02 20:37:04','CQABbxTrqrvJ7Tgnf4ge0DbWSpRYp1oF9Xwi27tN','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-03 14:45:06','CaR5x0CuR3Bd3iqJqrl70EqP99b8pSzvF4SVGZUu','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(43,'2025-09-03 15:04:48','MKrgFgPwzqF0XnWQkXhChoKfajK2bv4gaqdOZbQp','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(57,'2025-09-03 15:06:11','l40DjuYuqtPpqRN9EAsKLP22YK4ObOtXJBoda1ea','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-08 11:53:14','5rlSWM9iAp8cwdro6n52OsGetlXPfADG4iqQZA3U','145.236.180.172','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-08 15:33:59','y85Bf9CBCBoNFbW5d29h43klxzLBMzDhQgHrKWyM','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-08 20:57:07','b7dV3D3ZzjCrHYWAlG27oPCNmSItIkZnaIIVIwZ4','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-09 21:09:55','v3RDXoHZBtHGsXqGsSFcGrAmFiBJBpe4e76376HT','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-10 11:41:43','CBpSEaWuMYlB8tJNby0suSFkcMLVkcwP0V0Weg38','145.236.180.135','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-10 16:24:35','26VJ0uzBuk8hAV3CHX1tDTZjIlPjwMA7PiCer9wH','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-10 21:43:46','1R0mzRuq9YygZYnbupssyMjL8ca9tb4x7g63dspY','2001:4c4e:1840:7c00:cf9f:f310:d9b2:2da0','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-10 21:54:28','pNehKl85sIwn3mMaaom3jTikcZzEqcioQCJwNa2G','2001:4c4e:1840:7c00:cf9f:f310:d9b2:2da0','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-11 08:38:01','MYpgmFZHder0pSv0JwxmOngpgh7ehpu8ron0HHVE','91.120.121.15','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-11 19:24:00','rXWsjzG6L0rNaPnKjKZMqveXsbXEigFKlEdWESK5','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-11 19:26:21','1h14EWI1ocke6rsesp9lqI3etlly04GWWtvGUNSa','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-12 09:26:13','Go90j7fl8QKxSmOaYFLZZEpnGNadk1ZXtrFZP2UL','91.120.126.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-12 15:20:31','OtUQ0VrwjHlGptlhoJ3tYwCMY73nbQ6p1pilprVj','85.66.67.75','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-13 12:53:55','GWP8TuoM8mKNKJLSfDCt7snXxAcLVSM0KRiLrF9M','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(57,'2025-09-13 17:38:17','J6wH7HPMW0vmausKOFvvSEtFZPiSTgKSzGZZF0jx','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-13 17:53:56','UEPaYAlrKMk3UQBaUXirQWF1rJqDmM8goYpuyQxs','94.44.229.243','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-14 20:00:09','BsUZkhPx5BzLcNIx4XefFZ7vyXytBoaqHqBHRuQ0','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-15 00:11:45','IcgyhZUXkSLBcMx4ODOzaWnAfhGaNY69Mn7O56RM','85.66.68.150','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-15 07:57:24','z1AJvN96hCx4uraXexcn6pnvIKdEF62KaQ6D15Dq','85.66.68.150','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-15 10:03:10','BvBUelr2zcxQRX198qleF6wWXB5apaR4tAfywWdw','91.120.121.116','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-15 15:55:43','tPUl2TnYfVkorzVpwY2RzlyxQKSeuV3hxNeF0wRF','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-15 20:59:32','fLXlPlTong8mxAdmESISOTFnIFJSepj0TmGqLsme','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-16 09:10:33','P1y5tHq9urA2INF11Z237xN8xguWIYj5UfaETbAZ','91.120.121.116','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-16 11:42:13','2AQkqTQmDqt4ovb6AHQugxlEIpcMeMKA2KFQnES2','91.120.121.116','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-16 15:10:29','f3WqFmSI01tCW6QFtIxChl5lF7n4QJqHxVW1cPDa','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(43,'2025-09-16 19:06:45','u5IWZa1qcNY76jFnpWSOkM1SuoLyz7KS0JDpyFzc','85.66.68.150','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-16 19:07:16','i7n3gZOEi8EOepAHHRQMSC34CTlOftsailGswsyU','85.66.68.150','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-16 20:58:17','Nh5WhMFPuNpzLHNs0z9kh2zutmzgsIDD7i397rqT','2001:4c4e:1848:a500:f90e:98ed:78df:ed92','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-17 08:05:23','CljrkEviXhue1U36iruGwBOSOcGFm83AZDZVDpOS','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-17 18:07:06','PwUPYu9rzazcGC0aallSRvEAFz3rooN1NtaM0Hn8','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(62,'2025-09-17 18:52:48','PKUUx7IxS8xMbZbv73bnEkN9zEwGDCIFBDeh7kJy','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(59,'2025-09-17 18:55:30','CMQ5h107DcNAlaknVz7BGznwPG1PjdYUyEBIP3BT','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(57,'2025-09-17 19:30:34','iCkHwEoGS5HKwimketRYxBc4VV6dWfAF3KSNu44i','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(59,'2025-09-17 19:34:31','uZmAImylpqrKKHFrDlFWyxvjSUzMIKQb1gJj6Gwf','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(59,'2025-09-17 19:51:03','w43Ru24JtpvlSPVp5j3v2vzLyI5eh63x0hh3KFoI','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(61,'2025-09-17 20:28:55','Ixyx6CvhqWSM9NV7IgYH3rjajMXpIYrtpPW8vTU1','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(62,'2025-09-17 20:29:32','3BVop64hh0z8olIC7FFEYmt3gkLrF8ITGLetnVzo','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(44,'2025-09-17 20:31:42','sC7OueUauFZQ4GBAq0pHrGA7eliofFiEZg7VW0YZ','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(62,'2025-09-17 20:34:06','r1IKrR19BgIDQC1b3V6XYG5EQvlh4sggiWlHEYWp','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(61,'2025-09-17 20:35:09','JegakXXSAbxqADZkqKdYvVVx2M1Ry77xr1ieWRPQ','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(61,'2025-09-17 21:06:35','qtwPrg2FkFLiIWSyjt6UDoNOYNvaJqdKI13zmgKN','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(62,'2025-09-17 21:41:56','bsj4rNXoyd8xBvfPPgsepPo0wXB04A4HoyDTMJwv','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(62,'2025-09-17 22:06:10','v7RIsdRH0ikUGS0RYp9OS6VwcVW51EBHQ1pW4tfr','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(1,'2025-09-18 20:17:02','Hdt2X4ISA1lStvBSHS5LTPg4EOFCkiQHystixOgd','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-19 07:19:53','leqeQ9NOUtsJRG3lgCIUCgyLC6r4v43CuzNHF9cN','85.66.68.150','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-19 16:06:01','VsRmx4FeqrN5Zk547tJmS6Oqir4pBVSMiUkUqXpU','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-19 16:33:08','fxHHYxa0kk4aKkr47G4a0eGWpd8jWJZK1OZSQN92','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-19 16:35:17','5aU0vcpOdnXoDVYUP02TQKdDX123it8hyYHa2Ojt','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-19 16:35:25','2ivz61qnW7zuopZ9TNS7L8ob9YhE8wWGdyKvSHuS','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(43,'2025-09-19 16:35:53','AvRnshkOVO1bsHDD4PWb3D0DJdhjpr1G0isja12B','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(57,'2025-09-19 16:36:10','dgxoovDMXA2lDu7S9k8SYZ6gAsYVRu0WpzVwszSP','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-19 19:59:01','YXazkPVzHpMdIBwWitMD7RO3EaRkIrbtmEYvAgM1','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(57,'2025-09-19 19:59:19','MGQ5LSQjKfz5TfBv8BV6wqp63zEVopJUQdvLAzSR','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-19 20:11:42','F6xORtAQdr0WGVM0QYBq0DLa21Ru0V45kwh1N3rv','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-20 12:39:43','K7OIdlMlLWBq653lpV1StnMbzyT1xmMBc5LobEcI','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(69,'2025-09-21 08:25:02','jO12fSIAtUq0h4FagWaNZQxYtJoS8MrRMDtWrqcS','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-21 08:54:49','gdJl9Vtk6GS3WbOh3XQfopGgMwJU76I1sWXzJ6xU','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-21 09:02:16','VSJWxpB3pBfKgCi4SA4yVdHK28Y58Wd77jtqJhUz','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-21 09:02:38','QqjGalwDVHRc7xFZkqO6gFF4XZ6pbsatqxKFTA9N','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-21 09:03:55','hQZg3tMrU2mEcnP3uZUNoxKvweRPUei2DK1OL3B4','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-21 09:48:32','6z1WrzP4cJ8ad8PXPZ3r8zdwB5WQ5r0ms2jYi67P','85.66.67.75','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-21 20:41:09','ohYqmdpASmWMqe7aDQLGRpD9oMXc07QMBnq2Y3JM','188.6.38.51','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-23 18:10:54','ySaaQXgjxS0a0pjBYp8kGE4CD26BINpLGsLNpKfH','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-23 22:41:11','d6KlCALGQ9b9yyhh92iNgHnuFXN4mzDDFz3kPeX3','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-24 10:39:44','TfKuCu4QJXi6eYb96TLA5VzUco0tWv465mIblG4y','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-24 18:07:58','52wcEZxTWS4BPVQ2a5U8Y3aVRxNkWOOMmSxEft5w','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-24 20:01:50','GnlpDywIKWUTEB3V04Y8AbxSFPGtyDd8gPr4Vn1u','85.66.68.150','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-24 21:23:48','OOcE7ERE96nb7AYgxLiB0mDTgWJT2HVIzN5lF65A','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-26 09:50:14','FjF4Zwtq5jdeXDwxeX6graEZkgk01D9fB4WkZ1gQ','91.120.114.87','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-26 14:56:03','Tht52BWVeSLrpa36FsGgdegbRdxhJJ5rdjQ4plta','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-27 10:03:27','Wxh5zNy0Xcq5buvylQXBJqEmlj34zSwgdtx74ksw','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-27 18:28:49','oH88shjGlLNWQ43qNneUFHmI3fo51Ns2bNtlKqwu','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(59,'2025-09-27 18:34:07','6xgwXVE7Vwu2ljtWP1CHmcIh8hxXZwv4cAC3Nccy','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(61,'2025-09-27 18:36:39','27HLtH9qkiJd6DAAfTajpQcSS8CwEaAjzCjwGfaw','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-27 21:42:04','i0r1H2TwISulwJmDfUkZWqLWP7R4W5oHbupNoN66','2001:4c4e:1852:af00:4118:8e86:66bb:3c85','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(59,'2025-09-27 21:58:24','sWL9u5wZGHTLsQN1GkDIifssMv12F3WCTz9hstof','2001:4c4e:1852:af00:4118:8e86:66bb:3c85','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-28 09:41:16','Kcsl7Ubz0IE5NiDemtiyh5AX6BsSpQf2hN6zTTk5','188.6.37.24','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-28 19:52:01','5jxnhwbcm7NRqIBiCLKADTvQvvZVFoS6j1LoThNu','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-28 22:32:41','gWOPy8oyxZEALJBagteOvbYXCmADFNIs6ehMnRca','85.66.68.150','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-29 11:58:34','4qpzLDL8U8LYpP98rfHBhzIgTgt4pIJfpuzLDQt9','91.120.114.103','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-09-29 14:40:48','bapCE544IA74kF99T5mQsNorFmMvjmV2D4CyhGJ1','94.44.234.211','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-09-29 16:39:16','79QxgKs8Im5Wo1leWpP8VWCAoUsvtUl4wRfxWvTv','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-01 09:41:19','ewFUNsnP5YyJv3fUwmcWIoTu4w3353hqUNqfLtk1','91.120.114.103','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-02 18:27:53','NwBkp9eAnif8C0nCVz2VJ1QGbN4PuN2tQqzlnQG5','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-03 16:42:05','Z7HbQg3wT14olZVAfN6giPMTlghsWJ8nsqxA12jp','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(69,'2025-10-03 18:29:14','G4orfxpD44oohWJ2hK3036SnK1x2LEMGgkRHGLZT','85.66.68.150','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-10-04 11:13:09','fRbNkTEJ6IPbgJWQGMWehhzx4dQ9RFV5wOR0R2lO','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(77,'2025-10-04 19:59:59','oF2JcjGOPW9NJWlmX4V97Egq0q3YIhM8Bq4ULkOj','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(79,'2025-10-05 09:31:51','wil2MqiK4iDu8hTYpAkUBDhYMaCfoKcc5vLez12H','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-05 09:32:27','bXh7GxmnRY765vH82qKFWSoMdLXFFu80c8cqRPRD','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(79,'2025-10-05 10:11:42','IdedDqV1cU0W6ZJB86hSwFcYfgNwl2E7KqdT3m53','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-05 18:43:54','1C7M8rhlZcuWYipKTn0w6oK33AZRxrPeLmoDLvQy','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-06 00:26:49','3NDxlAy3T4uXdGbfUo2Ia4fwruOF6JjwwZ1YTE3m','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(79,'2025-10-06 01:02:48','ZDHcqfslNitGOLHuXeyIAHCzGY9DeB1dkhYlK5Sz','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-06 01:28:53','atFeuNtqe9AsrozlFENAI2OWpWvXgS7gLuh3To5m','85.66.68.150','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
(1,'2025-10-06 10:02:28','uktwQl1Tak68n6vASDh79UyiavWmZsvdIKkIhTJk','91.120.119.148','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-06 15:36:25','jHAg3hNNcHpLX4kxymzK8x3ArEdolrQGlbNsv82w','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-06 15:53:18','zIUiVA4gKeDA2eIYkX4TIslHGlhocUyD9ryTr7uK','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-06 16:23:49','ZJ1Azbgew3rAx9Zyf3Q2wTt0n29t2xnfniosYbEv','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-06 18:54:24','YPi2FL7I3I9X351HxblGPnYjGpJaSNtU3JxG0YnZ','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-06 20:15:50','1NLZEI0Xl8QlsqI4JZlUfb9bJN8AI0Rm3ofrMF35','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(56,'2025-10-06 20:25:47','JlUSWJMw96GsQEQzoNe68Vf6RAy698ZxUQrOy9D1','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-06 22:17:33','BlaArbHo2NChegXlSqF7nYMoAmvSXVkZ9hOhjxqz','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-07 07:52:52','XuJhTzP0hvNH5ptlGHQgNwX6btf7DWQO78vU8c55','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-07 10:12:40','9QciQ7J9gIeDZ3QVtcTr8yrsH4qqhZ4nypUjQ5FD','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-07 11:34:33','ZS0BNjQ01GJAU6VStfzVC9CfGPFGFFlboAAeu5yt','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-08 20:20:18','UXjSTC8a4MbDxt9IVlaaIt3MnB21NlUg2hyTr6ur','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-09 08:42:03','dd1UA7UqkQ0MP8O0O0gP6shPxpAEAKtrxjh8B1mA','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-09 10:57:12','4kDd96mGxtKWaQMAO2CYTnEdolcNz5BldMoki5Uo','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-09 21:48:18','RNf66OzFiNbuceaXUhwWRFfsgRz99v6ZnSoQ1XY6','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-09 22:19:20','jpDXoFdVlfBIi5EQFITUuKvqxaeG3TL5GZvcRQjx','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-11 12:26:17','SkHfJdn7dDLN6SCrrObwP1j2dQ7lIT0V1pWOussx','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-13 13:15:42','9Zuy6rU0GxjhuOn9NkufmHjTPBHUxoXnBh9n3Aqw','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-13 13:15:58','9tsf4uz8qdEZ9NgR7L06t9gpXHyJHYw7cuPyMVa4','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-13 13:17:13','yvT6WrkZdQ9NHuRzm8LheYXYwaISnAPddp1gtPV2','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-13 14:35:16','V5m5kEfELU9RgLNNTtZfBBh9sqfodlvXj9riQ9bN','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-13 18:03:51','hd8av1xXw2Og6iYUVSvwWCSB14ySyjghERBLNnIU','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-13 19:15:09','LqZaLNvTLLeHqj8CyVTAVw5x5JUHjjFpN553nPFL','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(56,'2025-10-13 19:15:16','axpm5rasVagWYuAK46tbQGlhDFWrjE1E9wifaeDQ','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-13 19:52:21','WhUEvKekTyZDkS8Xmu9sJZlirtUFoY6ne88DzaBm','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(56,'2025-10-13 20:09:50','as1Ya6YI6HsK8JP3di2fuYTqv9V24SepAzLk6963','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-13 21:02:10','r7mBjCek4b30TtiH1zGVx353ooB3Vnb6NmLqXIqp','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(78,'2025-10-13 21:03:49','QH0BFVqeEnohD4Yer5rfwV7VC8SfFlx8tDaGptOC','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-13 21:06:15','VYbx93ObDJB6RjMJyfLMneD6ukYdl2oARbHYJ93f','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-14 21:58:42','8kI415ffo6itIq5ark3T7z9MiquKnM21x5rN8fap','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-15 09:53:50','IDLR1lLiRPPOLSkJE40Trm7pDtlZ99MEOuxT1Gi2','188.6.37.84','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(1,'2025-10-15 15:01:29','s8k2RMc7Vt8vCFjSafm3grE0tkZnCvnO4modQjqI','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-15 19:05:46','mMKko7G74HXcdBdGbFoMrZdTigACuRQnNHniDatu','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(1,'2025-10-15 20:15:40','BgfzbzcMjYlO8vmb2Ks8YKwObQPKKWf3GkAlgHzc','85.66.68.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36');
/*!40000 ALTER TABLE `user_login` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_relation`
--

DROP TABLE IF EXISTS `user_relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_relation` (
  `user_id` bigint(20) unsigned NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  UNIQUE KEY `uq_user_relation_one` (`organization_id`,`user_id`,`target_id`),
  KEY `user_relation_fk1_idx` (`user_id`),
  KEY `user_relation_fk2_idx` (`target_id`),
  KEY `idx_user_relation_org_a2` (`organization_id`),
  KEY `idx_user_relation_org_user` (`organization_id`,`user_id`),
  CONSTRAINT `fk_user_relation_org_a2` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_relation_fk1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_relation_fk2` FOREIGN KEY (`target_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_relation`
--

LOCK TABLES `user_relation` WRITE;
/*!40000 ALTER TABLE `user_relation` DISABLE KEYS */;
INSERT INTO `user_relation` VALUES
(43,43,'self',1),
(43,55,'colleague',1),
(43,56,'colleague',1),
(43,57,'colleague',1),
(55,55,'self',1),
(55,56,'colleague',1),
(55,57,'colleague',1),
(55,58,'colleague',1),
(56,43,'colleague',1),
(56,56,'self',1),
(56,57,'colleague',1),
(56,58,'colleague',1),
(57,43,'subordinate',1),
(57,55,'subordinate',1),
(57,56,'subordinate',1),
(57,57,'self',1),
(57,58,'subordinate',1),
(57,70,'subordinate',1),
(58,55,'colleague',1),
(58,57,'colleague',1),
(58,58,'self',1),
(70,57,'colleague',1),
(70,70,'self',1),
(82,82,'self',1),
(83,83,'self',1),
(84,84,'self',1),
(59,59,'self',5),
(59,60,'colleague',5),
(59,61,'colleague',5),
(60,59,'subordinate',5),
(60,60,'self',5),
(60,61,'colleague',5),
(61,59,'subordinate',5),
(61,60,'colleague',5),
(61,61,'self',5),
(61,62,'colleague',5),
(62,59,'subordinate',5),
(62,60,'subordinate',5),
(62,61,'subordinate',5),
(62,62,'self',5),
(63,59,'colleague',5),
(63,62,'colleague',5),
(63,63,'self',5),
(37,37,'self',6),
(37,37,'self',7),
(42,42,'self',8);
/*!40000 ALTER TABLE `user_relation` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `check_user_relation_org` BEFORE INSERT ON `user_relation` FOR EACH ROW BEGIN
  IF NOT EXISTS (
    SELECT * FROM organization_user
    WHERE user_id = NEW.user_id
  ) THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'user_id not in any org';
  END IF;

  IF NOT EXISTS (
    SELECT * FROM organization_user
    WHERE user_id = NEW.target_id
  ) THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'target_id not in any org';
  END IF;

  IF NOT EXISTS (
    SELECT * FROM organization_user ou1
    INNER JOIN organization_user ou2 
      ON ou1.organization_id = ou2.organization_id
    WHERE ou1.user_id = NEW.user_id 
      AND ou2.user_id = NEW.target_id
  ) THEN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'users not in the same org';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `user_wages`
--

DROP TABLE IF EXISTS `user_wages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_wages` (
  `user_id` bigint(20) unsigned NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `net_wage` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'HUF',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`organization_id`),
  KEY `idx_org` (`organization_id`),
  CONSTRAINT `user_wages_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_wages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_wages`
--

LOCK TABLES `user_wages` WRITE;
/*!40000 ALTER TABLE `user_wages` DISABLE KEYS */;
INSERT INTO `user_wages` VALUES
(57,1,400000.00,'HUF','2025-10-14 20:46:51'),
(92,1,400000.00,'HUF','2025-10-14 20:46:51');
/*!40000 ALTER TABLE `user_wages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `webhook_events`
--

DROP TABLE IF EXISTS `webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `external_id` varchar(64) NOT NULL,
  `event_signature` varchar(64) NOT NULL,
  `source_ip` varchar(45) NOT NULL,
  `status` enum('processing','completed','failed') NOT NULL DEFAULT 'processing',
  `request_data` text DEFAULT NULL,
  `response_data` text DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webhook_events_event_signature_unique` (`event_signature`),
  KEY `webhook_events_created_at_index` (`created_at`),
  KEY `webhook_events_event_type_external_id_created_at_index` (`event_type`,`external_id`,`created_at`),
  KEY `webhook_events_event_type_index` (`event_type`),
  KEY `webhook_events_external_id_index` (`external_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `webhook_events`
--

LOCK TABLES `webhook_events` WRITE;
/*!40000 ALTER TABLE `webhook_events` DISABLE KEYS */;
INSERT INTO `webhook_events` VALUES
(1,'barion.payment','test-payment-123','266898267804039b206d5ac012db65753f274cb4ab80345dd18e0e5e8abe4ccd','37.9.175.172','failed','{\"PaymentId\":\"test-payment-123\"}','{\"error\":\"HTTP request returned status code 400:\\n{\\\"Errors\\\":[{\\\"Title\\\":\\\"Model Validation Error\\\",\\\"Description\\\":\\\"The value \'test-payment-123\' is not valid for PaymentId.\\\",\\\"E (truncated...)\\n\",\"trace\":\"#0 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Http\\/Client\\/Response.php(317): Illuminate\\\\Http\\\\Client\\\\Response->toException()\\n#1 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Services\\/BarionService.php(78): Illuminate\\\\Http\\\\Client\\\\Response->throw()\\n#2 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Controllers\\/PaymentWebhookController.php(87): App\\\\Services\\\\BarionService->getPaymentState(\'test-payment-12...\')\\n#3 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Controller.php(54): App\\\\Http\\\\Controllers\\\\PaymentWebhookController->barion(Object(Illuminate\\\\Http\\\\Request), Object(App\\\\Services\\\\BarionService), Object(App\\\\Services\\\\BillingoService))\\n#4 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/ControllerDispatcher.php(44): Illuminate\\\\Routing\\\\Controller->callAction(\'barion\', Array)\\n#5 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Route.php(266): Illuminate\\\\Routing\\\\ControllerDispatcher->dispatch(Object(Illuminate\\\\Routing\\\\Route), Object(App\\\\Http\\\\Controllers\\\\PaymentWebhookController), \'barion\')\\n#6 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Route.php(212): Illuminate\\\\Routing\\\\Route->runController()\\n#7 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Router.php(808): Illuminate\\\\Routing\\\\Route->run()\\n#8 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(170): Illuminate\\\\Routing\\\\Router->{closure:Illuminate\\\\Routing\\\\Router::runRouteWithinStack():807}(Object(Illuminate\\\\Http\\\\Request))\\n#9 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Middleware\\/BarionWebhookIpWhitelist.php(34): Illuminate\\\\Pipeline\\\\Pipeline->{closure:Illuminate\\\\Pipeline\\\\Pipeline::prepareDestination():168}(Object(Illuminate\\\\Http\\\\Request))\\n#10 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): App\\\\Http\\\\Middleware\\\\BarionWebhookIpWhitelist->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#11 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Middleware\\/CookieConsentMiddleware.php(38): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#12 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): App\\\\Http\\\\Middleware\\\\CookieConsentMiddleware->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#13 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/app\\/Http\\/Middleware\\/SetLocale.php(44): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#14 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): App\\\\Http\\\\Middleware\\\\SetLocale->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#15 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Middleware\\/SubstituteBindings.php(51): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#16 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Routing\\\\Middleware\\\\SubstituteBindings->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#17 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Middleware\\/ThrottleRequests.php(161): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#18 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Middleware\\/ThrottleRequests.php(127): Illuminate\\\\Routing\\\\Middleware\\\\ThrottleRequests->handleRequest(Object(Illuminate\\\\Http\\\\Request), Object(Closure), Array)\\n#19 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Middleware\\/ThrottleRequests.php(89): Illuminate\\\\Routing\\\\Middleware\\\\ThrottleRequests->handleRequestUsingNamedLimiter(Object(Illuminate\\\\Http\\\\Request), Object(Closure), \'webhook\', Object(Closure))\\n#20 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Routing\\\\Middleware\\\\ThrottleRequests->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure), \'webhook\')\\n#21 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/VerifyCsrfToken.php(88): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#22 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\VerifyCsrfToken->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#23 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/View\\/Middleware\\/ShareErrorsFromSession.php(49): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#24 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\View\\\\Middleware\\\\ShareErrorsFromSession->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#25 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Session\\/Middleware\\/StartSession.php(121): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#26 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Session\\/Middleware\\/StartSession.php(64): Illuminate\\\\Session\\\\Middleware\\\\StartSession->handleStatefulRequest(Object(Illuminate\\\\Http\\\\Request), Object(Illuminate\\\\Session\\\\Store), Object(Closure))\\n#27 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Session\\\\Middleware\\\\StartSession->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#28 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Cookie\\/Middleware\\/AddQueuedCookiesToResponse.php(37): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#29 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Cookie\\\\Middleware\\\\AddQueuedCookiesToResponse->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#30 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Cookie\\/Middleware\\/EncryptCookies.php(75): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#31 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Cookie\\\\Middleware\\\\EncryptCookies->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#32 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(127): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#33 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Router.php(807): Illuminate\\\\Pipeline\\\\Pipeline->then(Object(Closure))\\n#34 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Router.php(786): Illuminate\\\\Routing\\\\Router->runRouteWithinStack(Object(Illuminate\\\\Routing\\\\Route), Object(Illuminate\\\\Http\\\\Request))\\n#35 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Router.php(750): Illuminate\\\\Routing\\\\Router->runRoute(Object(Illuminate\\\\Http\\\\Request), Object(Illuminate\\\\Routing\\\\Route))\\n#36 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Routing\\/Router.php(739): Illuminate\\\\Routing\\\\Router->dispatchToRoute(Object(Illuminate\\\\Http\\\\Request))\\n#37 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Kernel.php(201): Illuminate\\\\Routing\\\\Router->dispatch(Object(Illuminate\\\\Http\\\\Request))\\n#38 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(170): Illuminate\\\\Foundation\\\\Http\\\\Kernel->{closure:Illuminate\\\\Foundation\\\\Http\\\\Kernel::dispatchToRouter():198}(Object(Illuminate\\\\Http\\\\Request))\\n#39 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/TransformsRequest.php(21): Illuminate\\\\Pipeline\\\\Pipeline->{closure:Illuminate\\\\Pipeline\\\\Pipeline::prepareDestination():168}(Object(Illuminate\\\\Http\\\\Request))\\n#40 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/ConvertEmptyStringsToNull.php(31): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\TransformsRequest->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#41 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\ConvertEmptyStringsToNull->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#42 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/TransformsRequest.php(21): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#43 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/TrimStrings.php(51): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\TransformsRequest->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#44 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\TrimStrings->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#45 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Http\\/Middleware\\/ValidatePostSize.php(27): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#46 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Http\\\\Middleware\\\\ValidatePostSize->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#47 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Middleware\\/PreventRequestsDuringMaintenance.php(110): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#48 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\PreventRequestsDuringMaintenance->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#49 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Http\\/Middleware\\/HandleCors.php(49): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#50 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Http\\\\Middleware\\\\HandleCors->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#51 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Http\\/Middleware\\/TrustProxies.php(58): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#52 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(209): Illuminate\\\\Http\\\\Middleware\\\\TrustProxies->handle(Object(Illuminate\\\\Http\\\\Request), Object(Closure))\\n#53 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Pipeline\\/Pipeline.php(127): Illuminate\\\\Pipeline\\\\Pipeline->{closure:{closure:Illuminate\\\\Pipeline\\\\Pipeline::carry():184}:185}(Object(Illuminate\\\\Http\\\\Request))\\n#54 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Kernel.php(176): Illuminate\\\\Pipeline\\\\Pipeline->then(Object(Closure))\\n#55 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/vendor\\/laravel\\/framework\\/src\\/Illuminate\\/Foundation\\/Http\\/Kernel.php(145): Illuminate\\\\Foundation\\\\Http\\\\Kernel->sendRequestThroughRouter(Object(Illuminate\\\\Http\\\\Request))\\n#56 \\/data\\/0\\/d\\/0dd1d87e-624b-40cb-ab35-e68edfa2099e\\/nwbusiness.hu\\/sub\\/staging\\/public\\/index.php(50): Illuminate\\\\Foundation\\\\Http\\\\Kernel->handle(Object(Illuminate\\\\Http\\\\Request))\\n#57 {main}\"}','2025-10-06 09:30:02','2025-10-06 09:30:02','2025-10-06 09:30:02'),
(3,'barion.payment','0370879367a8f0118c1e001dd8b71cc4','9047a92f714379045563126e64e30e933c1e7090b824291c3b492fa49317e87d','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"0370879367a8f0118c1e001dd8b71cc4\",\"paymentId\":\"0370879367a8f0118c1e001dd8b71cc4\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"barion_status\":\"CANCELED\",\"payment_id\":18,\"payment_status\":\"failed\",\"processing_time_ms\":194.33}','2025-10-13 19:05:32','2025-10-13 19:05:32','2025-10-13 19:05:32'),
(4,'barion.payment','2c6f85fdc7a9f0118c20001dd8b71cc5','3b33b0f67b4ad4a78e1fa1905ae87faee315aced86f498a7434dacc3ee2f50d3','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"2c6f85fdc7a9f0118c20001dd8b71cc5\",\"paymentId\":\"2c6f85fdc7a9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"barion_status\":\"EXPIRED\",\"payment_id\":11,\"payment_status\":\"pending\",\"processing_time_ms\":104.13}','2025-10-15 13:42:49','2025-10-15 13:42:49','2025-10-15 13:42:49'),
(5,'barion.payment','a7df05d6cca9f0118c20001dd8b71cc5','0271a3ce6f4f4e2b0abccb87109758df457bb533a6def19d78604e373cc387f2','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"a7df05d6cca9f0118c20001dd8b71cc5\",\"paymentId\":\"a7df05d6cca9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"CANCELED\"}','2025-10-15 13:42:55','2025-10-15 13:42:54','2025-10-15 13:42:55'),
(6,'barion.payment','dd04c9b8cda9f0118c20001dd8b71cc5','ae6e70a416265622344a070783b878996b833a15de526907a9412112ad86e957','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"dd04c9b8cda9f0118c20001dd8b71cc5\",\"paymentId\":\"dd04c9b8cda9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"CANCELED\"}','2025-10-15 13:49:26','2025-10-15 13:49:26','2025-10-15 13:49:26'),
(7,'barion.payment','9bb46ec6cda9f0118c20001dd8b71cc5','eb6ff93cf76b554a1daf05a364dc3ec913f2489ba161b9571ba1e6be954e67dd','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"9bb46ec6cda9f0118c20001dd8b71cc5\",\"paymentId\":\"9bb46ec6cda9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"CANCELED\"}','2025-10-15 13:49:36','2025-10-15 13:49:36','2025-10-15 13:49:36'),
(8,'barion.payment','404f10f4cda9f0118c20001dd8b71cc5','b6d380e138f2951de50f0b5c33ee9fd5854ded0cde1d5d2425dd54f5d920a36b','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"404f10f4cda9f0118c20001dd8b71cc5\",\"paymentId\":\"404f10f4cda9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"CANCELED\"}','2025-10-15 13:51:00','2025-10-15 13:51:00','2025-10-15 13:51:00'),
(9,'barion.payment','162f9063cea9f0118c20001dd8b71cc5','1549e1f622b63c49ed752501320f592b77a90bd9bc13d9dd0a9172fbd6cf095f','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"162f9063cea9f0118c20001dd8b71cc5\",\"paymentId\":\"162f9063cea9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"CANCELED\"}','2025-10-15 13:54:08','2025-10-15 13:54:08','2025-10-15 13:54:08'),
(10,'barion.payment','74d9167dcea9f0118c20001dd8b71cc5','2fdcefcfbdc23879b001c4993b7d4791c0f9d1732657e124cb548ed215e46f3e','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"74d9167dcea9f0118c20001dd8b71cc5\",\"paymentId\":\"74d9167dcea9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"barion_status\":\"CANCELED\",\"payment_id\":11,\"payment_status\":\"pending\",\"processing_time_ms\":89.26}','2025-10-15 13:54:44','2025-10-15 13:54:44','2025-10-15 13:54:44'),
(11,'barion.payment','d3de01c3cea9f0118c20001dd8b71cc5','c210ac7a3b7551a5aaab5c06ebb57d5c98c6e07dfc32cd486cf14cf72e50a33b','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"d3de01c3cea9f0118c20001dd8b71cc5\",\"paymentId\":\"d3de01c3cea9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"CANCELED\"}','2025-10-15 13:56:59','2025-10-15 13:56:59','2025-10-15 13:56:59'),
(12,'barion.payment','e1ac0bdccca9f0118c20001dd8b71cc5','f5bd2abd4fac8d4e632ab2f65919c54b18e170dc8f94c28c7cff423dca5d9b9e','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"e1ac0bdccca9f0118c20001dd8b71cc5\",\"paymentId\":\"e1ac0bdccca9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"EXPIRED\"}','2025-10-15 14:17:49','2025-10-15 14:17:49','2025-10-15 14:17:49'),
(13,'barion.payment','0da8d3a5cfa9f0118c20001dd8b71cc5','c5e28a1f950b44531503b8258e67e73b99014b8ad889db5091ade0d7ea80c67b','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"0da8d3a5cfa9f0118c20001dd8b71cc5\",\"paymentId\":\"0da8d3a5cfa9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"CANCELED\"}','2025-10-15 14:22:42','2025-10-15 14:22:42','2025-10-15 14:22:42'),
(14,'barion.payment','9bb16a6cd2a9f0118c20001dd8b71cc5','013d95a42f0f6fb3914e99d64a06879e6f299d7a21e2ce524d35dba79d34eafd','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"9bb16a6cd2a9f0118c20001dd8b71cc5\",\"paymentId\":\"9bb16a6cd2a9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"barion_status\":\"CANCELED\",\"payment_id\":11,\"payment_status\":\"pending\",\"processing_time_ms\":142.02}','2025-10-15 14:22:52','2025-10-15 14:22:52','2025-10-15 14:22:52'),
(15,'barion.payment','5ca70b73d2a9f0118c20001dd8b71cc5','a74aced4f49a4ccf3d149e86b57333ac34a5b0cd2f8a884c4c79bc23ce9b0426','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"5ca70b73d2a9f0118c20001dd8b71cc5\",\"paymentId\":\"5ca70b73d2a9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"CANCELED\"}','2025-10-15 14:23:10','2025-10-15 14:23:10','2025-10-15 14:23:10'),
(16,'barion.payment','ceba1b79d2a9f0118c20001dd8b71cc5','dd7ba216bb5fd34ebf03069aa1a4a739a8519dd6db8c021592d428d78a010f5b','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"ceba1b79d2a9f0118c20001dd8b71cc5\",\"paymentId\":\"ceba1b79d2a9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"CANCELED\"}','2025-10-15 14:27:15','2025-10-15 14:27:15','2025-10-15 14:27:15'),
(17,'barion.payment','ca70b927d7a9f0118c20001dd8b71cc5','fa09017223e5ddcb0cf84bc58c6006f7a049c29438f45d5b598cd14f8a3b85de','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"ca70b927d7a9f0118c20001dd8b71cc5\",\"paymentId\":\"ca70b927d7a9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"barion_status\":\"CANCELED\",\"payment_id\":11,\"payment_status\":\"failed\",\"processing_time_ms\":90.67}','2025-10-15 14:56:51','2025-10-15 14:56:51','2025-10-15 14:56:51'),
(18,'barion.payment','10d5990ed3a9f0118c20001dd8b71cc5','39e87e49dd7189d0f140666db8dafc3355280c90ca91d31b93b5d8cc9e6202ae','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"10d5990ed3a9f0118c20001dd8b71cc5\",\"paymentId\":\"10d5990ed3a9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"EXPIRED\"}','2025-10-15 14:57:49','2025-10-15 14:57:49','2025-10-15 14:57:49'),
(19,'barion.payment','09ab9b7ad9a9f0118c20001dd8b71cc5','eac1b30c1fb2f7759a72108ba163c27e9afc6a4d90c01ea9e7e1a1ff10f74120','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"09ab9b7ad9a9f0118c20001dd8b71cc5\",\"paymentId\":\"09ab9b7ad9a9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"barion_status\":\"CANCELED\",\"payment_id\":11,\"payment_status\":\"failed\",\"processing_time_ms\":72.25}','2025-10-15 15:13:31','2025-10-15 15:13:31','2025-10-15 15:13:31'),
(20,'barion.payment','0019f480d9a9f0118c20001dd8b71cc5','ad1fdcf96cbe654c22929c1652b2bdcf4ab530b9e389fd3debcfe7f086affc96','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"0019f480d9a9f0118c20001dd8b71cc5\",\"paymentId\":\"0019f480d9a9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"barion_status\":\"CANCELED\",\"payment_id\":11,\"payment_status\":\"failed\",\"processing_time_ms\":112.21}','2025-10-15 15:13:58','2025-10-15 15:13:58','2025-10-15 15:13:58'),
(21,'barion.payment','6c255294d9a9f0118c20001dd8b71cc5','7b688db7fbe9e0b7e5401312ff237e18058b0d101fa7b99f412c4d1bb67ec6d2','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"6c255294d9a9f0118c20001dd8b71cc5\",\"paymentId\":\"6c255294d9a9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"barion_status\":\"CANCELED\",\"payment_id\":11,\"payment_status\":\"pending\",\"processing_time_ms\":81.93}','2025-10-15 15:14:12','2025-10-15 15:14:12','2025-10-15 15:14:12'),
(22,'barion.payment','758fcd33d7a9f0118c20001dd8b71cc5','e4181ebcfa4430bd04f082179bb3870dadfaf26346c9cdc31bfb671aef175581','20.223.214.216','completed','{\"body\":{\"PaymentId\":\"758fcd33d7a9f0118c20001dd8b71cc5\",\"paymentId\":\"758fcd33d7a9f0118c20001dd8b71cc5\"},\"headers\":{\"user_agent\":\"Barion\\/2.0 PaymentGateway\\/Callback (+https:\\/\\/www.barion.com)\",\"content_type\":\"application\\/x-www-form-urlencoded\"},\"suspicious_patterns\":[]}','{\"result\":\"payment_not_found\",\"barion_status\":\"EXPIRED\"}','2025-10-15 15:27:49','2025-10-15 15:27:49','2025-10-15 15:27:49');
/*!40000 ALTER TABLE `webhook_events` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-15 20:50:39
