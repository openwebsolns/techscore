<?php
/*
 * Local template for executing with Docker Compose.
 */

// Print errors to screen instead of e-mailing
Conf::$ERROR_HANDLER = '\error\PrintHandler';

Conf::$WRITER = '\writers\LocalHtmlWriter';
Conf::$WRITER_PARAMS = array(
    \writers\LocalHtmlWriter::PARAM_HTML_ROOT => realpath(dirname(__FILE__).'/../public-html'),
);

Conf::$EMAIL_SENDER = '\mail\senders\StderrMailSender';
