BEGIN;
UPDATE metadata SET type = 'http://www.w3.org/2001/XMLSchema#float' WHERE property IN ('https://vocabs.acdh.oeaw.ac.at/schema#hasLongitude', 'https://vocabs.acdh.oeaw.ac.at/schema#hasLatitude');
COMMIT;
