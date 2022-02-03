BEGIN;
UPDATE relations SET property = 'https://vocabs.acdh.oeaw.ac.at/schema#isDerivedPublicationOf' WHERE property = 'https://vocabs.acdh.oeaw.ac.at/schema#isDerivedPublication';
COMMIT;
