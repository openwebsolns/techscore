-- Change TeamRotation to SailsList
UPDATE round SET rotation = REPLACE(rotation, '12:"TeamRotation"', '9:"SailsList"')
  WHERE rotation IS NOT NULL;
