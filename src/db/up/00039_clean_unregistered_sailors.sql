-- delete any that are not in RPs --
DELETE FROM sailor WHERE register_status = 'unregistered' AND id NOT IN (
  SELECT sailor FROM attendee WHERE sailor IS NOT NULL
);
