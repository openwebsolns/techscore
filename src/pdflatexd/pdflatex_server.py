#!/usr/bin/env python
# Python socket server which generates PDF documents and echoes back
# the result
#
# Error codes:
#   64: Something horrible went awry.
#    *: As returned by PDFLaTeX process

import socket
import signal
import os
import os.path
import subprocess
import logging
import tempfile

import params

# Setup the signal handlers for cleaning up after socket
def handler(signum, frame):
    logging.info('Signal handler called with signal ' + str(signum))
    if os.path.exists(params.HOST):
        logging.info('Removing file ' + params.HOST)
        os.unlink(params.HOST)
    # logging.shutdown()

# Set the signal handler and a 5-second alarm
signal.signal(signal.SIGALRM, handler)
signal.signal(signal.SIGHUP,  handler)

def run(code):
    """Runs PDFLaTeX on the given code, returning the created PDF
    document.
    
    Arguments:
    - `code`: the latex code to use

    Returns: the PDF binary data
    """
    logging.debug("got data")

    # build PDFLatex call
    (f, fname) = tempfile.mkstemp("", "ows-")
    sargs = [params.PDFLATEX,
             '-output-directory=%s' % os.path.dirname(fname),
             '-interaction=nonstopmode',
             '-jobname="%s"' % os.path.basename(fname),
             code]
    
    # logging.debug("building command: " + str(sargs))
    logging.debug("built command")

    pdf = ""
    try:
        ret = str(subprocess.call(sargs, stdout=open(os.devnull, 'w'), stderr=subprocess.STDOUT))
        if os.path.isfile(fname + ".pdf"):
            logging.debug("finished creating file")
            f = open(fname + ".pdf")
            pdf = f.read()
            f.close()
        else:
            pdf = ret
            logging.error("Returned value " + ret)

    except Exception as mes:
        logging.error("While generating PDF: " + str(mes))
        pdf = "64"

    # cleanup
    for ext in ['.log', '.aux', '.pdf', '']:
        nam = '%s%s' % (fname, ext)
        if os.path.isfile(nam):
            os.unlink(nam)

    return pdf


# SETUP logging
logging.basicConfig(filename=params.LOGFILE,
                    format="%(levelname)s\t%(asctime)s\t%(message)s",
                    level=params.LOGLEVEL)

# ------------------
# Create the socket
logging.info("started")
# delete stale socket
if os.path.isfile(params.HOST):
    os.unlink(params.HOST)
    logging.warning("deleted stale socket")
try:
    os.umask(0000)
    s = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    s.bind(params.HOST)
    s.listen(1)
    socket.setdefaulttimeout(0.5)
    os.umask(0077)
    while True:
        try:
            conn, addr = s.accept()
            size = conn.recv(8) # this is the size of the message
            mess = ""
            logging.debug("Connected, size: %s" % size)
            while len(mess) < int(size):
                data = conn.recv(1024)
                if not data: break
                mess += data
            ret = run(mess)
            amt = conn.sendall("%08d%s" % (len(ret), ret))
            logging.info("OK " + str(amt))
            conn.close()
        except Exception as mes:
            logging.error(mes)
except KeyboardInterrupt:
    logging.warning("stopped")
    handler(signal.SIGHUP, None)
except Exception as mes:
    logging.error(mes)
    handler(signal.SIGHUP, None)
