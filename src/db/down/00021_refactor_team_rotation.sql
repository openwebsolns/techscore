-- Change SailsList to TeamRotation
UPDATE round SET rotation = REPLACE(rotation, '9:"SailsList"', '12:"TeamRotation"')
  WHERE rotation IS NOT NULL;
