TRUNCATE TABLE data_intermediate_ad;
TRUNCATE TABLE data_intermediate_ad_connection;
TRUNCATE TABLE data_intermediate_ad_variable_value;
TRUNCATE TABLE data_raw_ad_click;
TRUNCATE TABLE data_raw_ad_impression;
TRUNCATE TABLE data_raw_ad_request;
TRUNCATE TABLE data_raw_tracker_click;
TRUNCATE TABLE data_raw_tracker_impression;
TRUNCATE TABLE data_raw_tracker_variable_value;
TRUNCATE TABLE data_summary_ad_hourly;
TRUNCATE TABLE data_summary_ad_zone_assoc;
TRUNCATE TABLE data_summary_channel_daily;
TRUNCATE TABLE data_summary_zone_country_daily;
TRUNCATE TABLE data_summary_zone_country_forecast;
TRUNCATE TABLE data_summary_zone_country_monthly;
TRUNCATE TABLE data_summary_zone_domain_page_daily;
TRUNCATE TABLE data_summary_zone_domain_page_forecast;
TRUNCATE TABLE data_summary_zone_domain_page_monthly;
TRUNCATE TABLE data_summary_zone_impression_history;
TRUNCATE TABLE data_summary_zone_site_keyword_daily;
TRUNCATE TABLE data_summary_zone_site_keyword_forecast;
TRUNCATE TABLE data_summary_zone_site_keyword_monthly;
TRUNCATE TABLE data_summary_zone_source_daily;
TRUNCATE TABLE data_summary_zone_source_forecast;
TRUNCATE TABLE data_summary_zone_source_monthly;
TRUNCATE TABLE log_maintenance_forecasting;
TRUNCATE TABLE log_maintenance_priority;
TRUNCATE TABLE log_maintenance_statistics;
TRUNCATE TABLE acls;
TRUNCATE TABLE acls_channel;
TRUNCATE TABLE ad_zone_assoc;
TRUNCATE TABLE affiliates;
TRUNCATE TABLE affiliates_extra;
TRUNCATE TABLE banners;
TRUNCATE TABLE campaigns;
TRUNCATE TABLE channel;
TRUNCATE TABLE clients;
TRUNCATE TABLE placement_zone_assoc;
TRUNCATE TABLE zones;
INSERT INTO acls (bannerid, logical, type, comparison, data, executionorder) VALUES (1,'and','Site:Channel','==','1',0);
INSERT INTO acls (bannerid, logical, type, comparison, data, executionorder) VALUES (1,'and','Site:Channel','==','2',1);
INSERT INTO acls_channel (channelid, logical, type, comparison, data, executionorder) VALUES (1,'and','Site:Pageurl','=~','example',0);
INSERT INTO acls_channel (channelid, logical, type, comparison, data, executionorder) VALUES (2,'and','Site:Referingpage','=~','refer.com',0);
INSERT INTO ad_zone_assoc (ad_zone_assoc_id, zone_id, ad_id, priority, link_type) VALUES (1,0,1,0,0);
INSERT INTO ad_zone_assoc (ad_zone_assoc_id, zone_id, ad_id, priority, link_type) VALUES (11,1,1,0,1);
INSERT INTO affiliates (affiliateid, agencyid, name, mnemonic, comments, contact, email, website, username, password, permissions, language, publiczones, last_accepted_agency_agreement, updated) VALUES (1,0,'Test Publisher','Test','','Monique Szpak','monique@m3.net','http://www.openx.org',NULL,'',0,'','f',NULL,'2006-11-06 11:49:36');
INSERT INTO affiliates_extra (affiliateid, address, city, postcode, country, phone, fax, account_contact, payee_name, tax_id, mode_of_payment, currency, unique_users, unique_views, page_rank, category, help_file) VALUES (1,'','','','','','','','','','Cheque by post','GBP',0,0,0,'','');
INSERT INTO banners (bannerid, campaignid, active, contenttype, pluginversion, storagetype, filename, imageurl, htmltemplate, htmlcache, width, height, weight, seq, target, url, alt, status, bannertext, description, autohtml, adserver, block, capping, session_capping, compiledlimitation, acl_plugins, append, appendtype, bannertype, alt_filename, alt_imageurl, alt_contenttype, comments, updated, acls_updated) VALUES (1,1,'t','',0,'html','','','test 1','test 1',468,60,1,0,'_blank','http://www.openx.org','','','','targeted 100000 ','t','',0,0,0,'(MAX_checkSite_Channel(\'1\', \'==\')) and (MAX_checkSite_Channel(\'2\', \'==\'))','Site:Channel','',0,0,'','','','','2006-12-04 15:13:43','2007-01-08 13:28:07');
INSERT INTO campaigns (campaignid, campaignname, clientid, views, clicks, conversions, expire, activate, active, priority, weight, target_impression, target_click, target_conversion, anonymous, companion, comments, revenue, revenue_type, updated) VALUES (1,'Test Advertiser - Default Campaign',1,100000,-1,-1,'2000-01-01','2000-01-01','t',5,0,700,0,0,'f',0,'','0.0000',0,'2006-12-04 15:13:28');
INSERT INTO channel (channelid, agencyid, affiliateid, name, description, compiledlimitation, acl_plugins, active, comments, updated, acls_updated) VALUES (1,0,1,'Test Channel - page url','','MAX_checkSite_Pageurl(\'example\', \'=~\')','Site:Pageurl',1,'','2000-01-01 00:00:00','2007-01-08 12:09:17');
INSERT INTO channel (channelid, agencyid, affiliateid, name, description, compiledlimitation, acl_plugins, active, comments, updated, acls_updated) VALUES (2,0,1,'Test Channel - Referrer','Test Channel - referrer = www.referrer.com','MAX_checkSite_Referingpage(\'refer.com\', \'=~\')','Site:Referingpage',1,'','2000-01-01 00:00:00','2007-01-08 12:32:27');
INSERT INTO clients (clientid, agencyid, clientname, contact, email, clientusername, clientpassword, permissions, language, report, reportinterval, reportlastdate, reportdeactivate, comments, updated) VALUES (1,0,'Test Advertiser','Monique Szpak','monique@m3.net','simon','b30bd351371c686298d32281b337e8e9',0,'','f',7,'2007-01-05','t','','2007-01-05 16:26:06');
INSERT INTO placement_zone_assoc (placement_zone_assoc_id, zone_id, placement_id) VALUES (2,1,1);
INSERT INTO trackers (trackerid, trackername, description, clientid, viewwindow, clickwindow, blockwindow, status, type, linkcampaigns, variablemethod, appendcode, updated) VALUES (1,'Test Advertiser - Default Tracker','Test Tracker',1,0,2592000,0,4,1,'t','dom','','2007-01-05 15:11:42');
INSERT INTO variables (variableid, trackerid, name, description, datatype, purpose, reject_if_empty, is_unique, unique_window, variablecode, hidden, updated) VALUES (1,1,'TestVar','Tracker Test Variable','string',NULL,0,0,0,'','f','2007-01-05 15:12:44');
INSERT INTO zones (zoneid, affiliateid, zonename, description, delivery, zonetype, category, width, height, ad_selection, chain, prepend, append, appendtype, forceappend, inventory_forecast_type, comments, cost, cost_type, cost_variable_id, technology_cost, technology_cost_type, updated, block, capping, session_capping) VALUES (1,1,'Test_Publisher - 468 x 60','Test Banner',0,3,'',468,60,'','','','',0,'f',0,'','0.0000',0,NULL,NULL,NULL,'2006-11-06 11:51:49',0,0,0);
INSERT INTO log_maintenance_statistics (log_maintenance_statistics_id, start_run, end_run, duration, adserver_run_type, search_run_type, tracker_run_type, updated_to) VALUES (1, '1970-01-01 01:00:00', '1970-01-01 01:00:00', 0, 2, NULL, NULL, '2007-01-09 11:59:59');
UPDATE ad_zone_assoc SET priority=0;
