SET @t = UNIX_TIMESTAMP();
INSERT INTO tl_theme (id, tstamp, name) VALUES (1, @t, 'Check');
INSERT INTO tl_layout (id, pid, tstamp, name, type, `rows`, cols, template, modules, viewport, script)
  VALUES (1, 1, @t, 'Check', 'default', '1rw', '1cl', 'fe_page', 'a:1:{i:0;a:3:{s:3:"mod";s:1:"0";s:3:"col";s:4:"main";s:6:"enable";s:1:"1";}}', 'width=device-width,initial-scale=1', '<script src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>');
INSERT INTO tl_page (id, pid, sorting, tstamp, title, alias, type, language, fallback, dns, published, includeLayout, layout, juheConsentBanner, useSSL, enableCsp, csp, includeCache, cache)
  VALUES (1, 0, 128, @t, 'Check', 'check', 'root', 'de', 1, '', 1, 1, 1, 1, 0, 1, "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'", 1, 300);
INSERT INTO tl_page (id, pid, sorting, tstamp, title, alias, type, published, robots)
  VALUES (2, 1, 128, @t, 'Start', 'index', 'regular', 1, 'index,follow'),
         (3, 1, 256, @t, 'Datenschutz', 'datenschutz', 'regular', 1, 'index,follow');
INSERT INTO tl_article (id, pid, sorting, tstamp, title, alias, inColumn, published) VALUES (1, 2, 128, @t, 'Start', 'start', 'main', 1);
INSERT INTO tl_content (id, pid, ptable, sorting, tstamp, type, youtube, linkTitle) VALUES
  (1, 1, 'tl_article', 128, @t, 'youtube', 'abcdefghijk', ''),
  (2, 1, 'tl_article', 256, @t, 'juhe_consent_settings', '', ''),
  (3, 1, 'tl_article', 384, @t, 'youtube', 'bcdefghijkl', '');
INSERT INTO tl_juhe_consent_banner (id, tstamp, title, headline, text, placeholderText, askOnFirstVisit, privacyPage, imprintPage, version)
  VALUES (1, @t, 'Check', 'Cookies', '<p>Wir verwenden Cookies.</p>', '', 1, 3, 0, 1);
INSERT INTO tl_juhe_consent_group (id, pid, sorting, tstamp, title, alias, essential, description, published) VALUES
  (1, 1, 128, @t, 'Notwendig', 'notwendig', 1, 'Damit die Website funktioniert.', 1),
  (2, 1, 256, @t, 'Marketing', 'marketing', 0, 'Videos und Statistik.', 1);
INSERT INTO tl_juhe_consent_service (id, pid, sorting, tstamp, title, provider, privacyUrl, description, cookies, hosts, code, published) VALUES
  (1, 1, 128, @t, 'Consent', 'Site', '', '', 'juhe_consent_1: 1 Jahr', '', '', 1),
  (2, 2, 128, @t, 'YouTube', 'Google', 'https://policies.google.com/privacy', '', '', 'youtube.com', '', 1),
  (3, 2, 256, @t, 'Tag Manager', 'Google', '', '', '_ga*: 2 Jahre', 'googletagmanager.com', '<script>window.juheCheckCode = 1;</script>', 1);
-- Batch A: an excluded page, a login page and a member
INSERT INTO tl_page (id, pid, sorting, tstamp, title, alias, type, published, robots)
  VALUES (4, 1, 384, @t, 'Kontakt', 'kontakt', 'regular', 1, 'index,follow'),
         (5, 1, 512, @t, 'Login', 'login', 'regular', 1, 'noindex,nofollow');
INSERT INTO tl_article (id, pid, sorting, tstamp, title, alias, inColumn, published) VALUES
  (2, 4, 128, @t, 'Kontakt', 'kontakt', 'main', 1),
  (3, 5, 128, @t, 'Login', 'login', 'main', 1);
INSERT INTO tl_module (id, pid, tstamp, name, type) VALUES (1, 1, @t, 'Login', 'login');
INSERT INTO tl_content (id, pid, ptable, sorting, tstamp, type, youtube, linkTitle, module) VALUES
  (4, 2, 'tl_article', 128, @t, 'youtube', 'cdefghijklm', '', 0),
  (5, 3, 'tl_article', 128, @t, 'module', '', '', 1);
INSERT INTO tl_member (id, tstamp, firstname, lastname, email, username, password, login, disable, dateAdded)
  VALUES (1, @t, 'Mia', 'Member', 'member@example.org', 'member', '$2y$12$yxRJX63xl/UVdBuCWoybZ.S2g5cQSl2NMGZ6mPNuApRuHPtRmb7EO', 1, 0, @t);
-- Batch C: a second website root (English, /en/) with its own banner 3
INSERT INTO tl_page (id, pid, sorting, tstamp, title, alias, type, language, fallback, dns, urlPrefix, useSSL, published, includeLayout, layout, juheConsentBanner, includeCache, cache)
  VALUES (10, 0, 256, @t, 'Check EN', 'check-en', 'root', 'en', 0, '', 'en', 0, 1, 1, 1, 3, 1, 300);
INSERT INTO tl_page (id, pid, sorting, tstamp, title, alias, type, published, robots) VALUES (11, 10, 128, @t, 'Home', 'home', 'regular', 1, 'index,follow');
INSERT INTO tl_article (id, pid, sorting, tstamp, title, alias, inColumn, published) VALUES (10, 11, 128, @t, 'Home', 'home-en', 'main', 1);
INSERT INTO tl_juhe_consent_banner (id, tstamp, title, headline, text, placeholderText, askOnFirstVisit, privacyPage, imprintPage, version)
  VALUES (3, @t, 'Check EN', 'Cookies EN', '<p>We use cookies.</p>', '', 1, 0, 0, 1);
INSERT INTO tl_juhe_consent_group (id, pid, sorting, tstamp, title, alias, essential, description, published) VALUES
  (10, 3, 128, @t, 'Necessary', 'necessary', 1, '', 1),
  (11, 3, 256, @t, 'Statistics', 'statistics', 0, '', 1);
INSERT INTO tl_juhe_consent_service (id, pid, sorting, tstamp, title, provider, privacyUrl, description, cookies, hosts, code, published) VALUES
  (10, 11, 128, @t, 'Stats', 'Example', '', '', '', '', '<script>window.juheEnCode = 1;</script>', 1);
