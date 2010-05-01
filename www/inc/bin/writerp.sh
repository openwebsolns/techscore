#!/bin/bash
# Script for creating an image of the RP form
# Taking information from a file

data="$1"

# Create temp folder
DIR=`mktemp -d`

# Make copy of PDF file
convert ICSA-RP-ABC.pdf $DIR/page.png

# Parameters for AB division page
L_REG='140,65' # Regatta location
L_HOS='325,65' # Host
L_DAT='490,65' # Date

# School
Y0_TEA='90'    # start y value for team
YD_TEA='330'   # delta y
X_TEA='90'     # x-location of team name
N_TEA='2'      # number of teams per page

# Rep
Y0_REP='90'
YD_REP='330'
X_REP='430'

# Sig
Y0_SIG='110'
YD_SIG='330'
X_SIG='315'

# A DIVISION
# Skippers
Y0_SK_A='172'
YD_SK_A='330'
Yd_SK_A='24'     # delta y from one skipper entry to the next in the
	       # same division 
X_SKN_A='60'     # Skipper name
X_SKY_A='200'    # Skipper year
X_SKR_A='230'    # Skipper race

# Crews
Y0_CR_A='332'
YD_CR_A='330'
Yd_CR_A='24'
X_CRN_A='60'
X_CRY_A='200'
X_CRR_A='230'

# B DIVISION
# Skippers
Y0_SK_B='172'
YD_SK_B='330'
Yd_SK_B='24'     # delta y from one skipper entry to the next in the
	       # same division 
X_SKN_B='60'     # Skipper name
X_SKY_B='200'    # Skipper year
X_SKR_B='230'    # Skipper race

# Crews
Y0_CR_B='332'
YD_CR_B='330'
Yd_CR_B='24'
X_CRN_B='60'
X_CRY_B='200'
X_CRR_B='230'

# C DIVISION
# Skippers
Y0_SK_C='172'
YD_SK_C='330'
Yd_SK_C='24'     # delta y from one skipper entry to the next in the
	       # same division 
X_SKN_C='60'     # Skipper name
X_SKY_C='200'    # Skipper year
X_SKR_C='230'    # Skipper race

# Crews
Y0_CR_C='332'
YD_CR_C='330'
Yd_CR_C='24'
X_CRN_C='60'
X_CRY_C='200'
X_CRR_C='230'

## WRITE DATA ##
convert $DIR/page.png -font Verdana-Regular -pointsize 10 -fill navy \
    -draw "text $L_REG '`head -1 $data | cut -f 1`'" \
    -draw "text $L_HOS '`head -2 $data | tail -1`'" \
    -draw "text $L_DAT '`head -3 $data | tail -1`'" \
    $DIR/page.png

for team in 1 2; do # Teams
    name=`grep "^Team$team" $data | cut -f 2`
    rep=`grep "^Team$team" $data | cut -f 3`

#     convert $DIR/page.png -font Verdana-Bold -pointsize 10 -fill navy  \
# 	-draw "text $
done


#    $DIR/`head -1 $data | cut -f 2`.png