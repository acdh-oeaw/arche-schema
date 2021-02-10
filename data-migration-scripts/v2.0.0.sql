BEGIN;

--------------------------------------------------------------------------------
-- class TopCollection introduced
--------------------------------------------------------------------------------
drop table if exists _topcol;
create temporary table _topcol as
    select nextval('mid_seq'), id, property, type, lang, value_n, value_t, 'https://vocabs.acdh.oeaw.ac.at/schema#TopCollection' as value from metadata m where 
        property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' 
        and value = 'https://vocabs.acdh.oeaw.ac.at/schema#Collection'
        and not exists (select 1 from relations where id = m.id and property = 'https://vocabs.acdh.oeaw.ac.at/schema#isPartOf')
        and not exists (select 1 from metadata where id = m.id and property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and value = 'https://vocabs.acdh.oeaw.ac.at/schema#TopCollection');
insert into metadata select * from _topcol;

--------------------------------------------------------------------------------
-- hasHosting ARCHE for all TopCollections
--------------------------------------------------------------------------------

insert into relations (id, property, target_id) 
    select m.id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasHosting', t.id 
    from metadata m, (select id from identifiers where ids = 'https://id.acdh.oeaw.ac.at/arche') t
    where 
    m.property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and m.value = 'https://vocabs.acdh.oeaw.ac.at/schema#TopCollection'
    and not exists (select 1 from relations r join identifiers i on r.target_id = i.id where m.id = r.id and i.ids = 'https://id.acdh.oeaw.ac.at/arche');

--------------------------------------------------------------------------------
-- class Image removed
--------------------------------------------------------------------------------
insert into metadata 
    select nextval('mid_seq'), id, property, type, lang, value_n, value_t, 'https://vocabs.acdh.oeaw.ac.at/schema#Resource'
    from metadata where property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and value = 'https://vocabs.acdh.oeaw.ac.at/schema#Image' on conflict do nothing;
delete from metadata where property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and value = 'https://vocabs.acdh.oeaw.ac.at/schema#Image';

--------------------------------------------------------------------------------
-- removed inverse properties
--------------------------------------------------------------------------------
insert into relations (id, target_id, property) select target_id, id, 'https://vocabs.acdh.oeaw.ac.at/schema#isTitleImageOf' from relations where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasTitleImage' on conflict do nothing;
delete from relations where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasTitleImage';
insert into relations (id, target_id, property) select target_id, id, 'https://vocabs.acdh.oeaw.ac.at/schema#isSourceOf' from relations where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasSource' on conflict do nothing;
delete from relations where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasSource';
insert into relations (id, target_id, property) select target_id, id, 'https://vocabs.acdh.oeaw.ac.at/schema#isDerivedPublication' from relations where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasDerivedPublication' on conflict do nothing;
delete from relations where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasDerivedPublication';

select * from relations where property in ('https://vocabs.acdh.oeaw.ac.at/schema#isContinuedBy', 'https://vocabs.acdh.oeaw.ac.at/schema#isDocumentedBy', 'https://vocabs.acdh.oeaw.ac.at/schema#isActorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isAuthorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isContactOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isContributorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isCoverageOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isCreatorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isCuratorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isDepositorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#hasDerivedPublication', 'https://vocabs.acdh.oeaw.ac.at/schema#isEditorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isFunderOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isHostingOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isLicensorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#hasMember', 'https://vocabs.acdh.oeaw.ac.at/schema#hasMetadata', 'https://vocabs.acdh.oeaw.ac.at/schema#isMetadataCreatorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isOwnerOf', 'https://vocabs.acdh.oeaw.ac.at/schema#hasPart', 'https://vocabs.acdh.oeaw.ac.at/schema#isPrincipalInvestigatorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isRightsHolderOf', 'https://vocabs.acdh.oeaw.ac.at/schema#hasSource', 'https://vocabs.acdh.oeaw.ac.at/schema#isSpatialCoverageOf', 'https://vocabs.acdh.oeaw.ac.at/schema#hasTitleImage', 'https://vocabs.acdh.oeaw.ac.at/schema#isPreviousVersionOf');

--------------------------------------------------------------------------------
-- hasCreatedDate is now hasCreatedStartDate and hasCreatedEndDate
--------------------------------------------------------------------------------
insert into metadata 
    select nextval('mid_seq'), id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasCreatedStartDate', type, lang, value_n, value_t, value 
    from metadata where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCreatedDate';
insert into metadata 
    select nextval('mid_seq'), id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasCreatedEndDate', type, lang, value_n, value_t, value 
    from metadata where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCreatedDate';
delete from metadata where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCreatedDate';

--------------------------------------------------------------------------------
-- hasCreatedDateOriginal is now hasCreatedStartDateOriginal and hasCreatedEndDateOriginal
--------------------------------------------------------------------------------
insert into metadata 
    select nextval('mid_seq'), id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasCreatedStartDateOriginal', type, lang, value_n, value_t, value 
    from metadata where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCreatedDateOriginal';
insert into metadata 
    select nextval('mid_seq'), id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasCreatedEndDateOriginal', type, lang, value_n, value_t, value 
    from metadata where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCreatedDateOriginal';
delete from metadata where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCreatedDateOriginal';

--------------------------------------------------------------------------------
-- hasCustomXsl instead of hasCustomXSL
--------------------------------------------------------------------------------
insert into metadata 
    select nextval('mid_seq'), r.id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasCustomXsl', 'http://www.w3.org/2001/XMLSchema#anyURI', '', null, null, ids
    from relations r join identifiers i on r.target_id = i.id and i.ids like 'https://id.acdh.oeaw.ac.at/%'
    where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCustomXSL';
delete from relations where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCustomXSL';
update metadata set property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCustomXsl' where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasCustomXSL';

--------------------------------------------------------------------------------
-- remove hasBinaryUpdatedDate, hasHash, hasFilename, hasRawBinarySize from resources which are non-binary ACDH classes
--------------------------------------------------------------------------------
delete from metadata m where
    property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasBinaryUpdatedDate'
    and exists (select 1 from metadata where id = m.id and property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and value in ('https://vocabs.acdh.oeaw.ac.at/schema#Collection', 'https://vocabs.acdh.oeaw.ac.at/schema#Person', 'https://vocabs.acdh.oeaw.ac.at/schema#Place', 'https://vocabs.acdh.oeaw.ac.at/schema#Organisation', 'https://vocabs.acdh.oeaw.ac.at/schema#Publication', 'https://vocabs.acdh.oeaw.ac.at/schema#Project', 'https://vocabs.acdh.oeaw.ac.at/schema#DisseminationServiceParameter'));

delete from metadata m where
    property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasFilename'
    and exists (select 1 from metadata where id = m.id and property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and value = 'https://vocabs.acdh.oeaw.ac.at/schema#Collection');

-- hasHash and hasRawBinarySize are Fedora heritage - they are binary resources, just should not have Collection class
delete from metadata m where
    property in ('https://vocabs.acdh.oeaw.ac.at/schema#hasHash', 'https://vocabs.acdh.oeaw.ac.at/schema#hasRawBinarySize')
    and exists (select 1 from metadata where id = m.id and property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and value = 'https://vocabs.acdh.oeaw.ac.at/schema#Collection')
    and exists (select 1 from identifiers where id = m.id and ids like 'https://vocabs.acdh.oeaw.ac.at/schema/%');

--------------------------------------------------------------------------------
-- other properties in the acdh namespace not mentioned in the schema
--------------------------------------------------------------------------------
select *
    from (select property, count(*) from metadata where property like 'https://vocabs.acdh.oeaw.ac.at%' group by 1) t
    where not exists (select 1 from identifiers where t.property = ids);
select *
    from (select property, count(*) from relations where property like 'https://vocabs.acdh.oeaw.ac.at%' group by 1) t
    where not exists (select 1 from identifiers where t.property = ids);

--------------------------------------------------------------------------------
-- acdh:hasAccessRestriction is now applicable only to acdh:BinaryContent
--------------------------------------------------------------------------------
select count(*) from relations where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasAccessRestriction';
select count(*) from metadata where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasNumberOfItems';
select count(*) from metadata where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasBinarySize';

create temporary table _binaryContent as select id, 'BinaryContent' as type from metadata where property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and value in ('https://vocabs.acdh.oeaw.ac.at/schema#Resource', 'https://vocabs.acdh.oeaw.ac.at/schema#Image', 'https://vocabs.acdh.oeaw.ac.at/schema#Metadata', 'https://vocabs.acdh.oeaw.ac.at/schema#BinaryContent');
delete from relations r where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasAccessRestriction' and not exists (select 1 from _binaryContent where r.id = id);

--------------------------------------------------------------------------------
-- acdh:hasNumberOfItems is now applicable only for acdh:Collection
--------------------------------------------------------------------------------
create temporary table _collections as select id, 'Collection' as type from metadata where property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and value = 'https://vocabs.acdh.oeaw.ac.at/schema#Collection';
delete from metadata m where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasNumberOfItems' and not exists (select 1 from _collections where m.id = id);

--------------------------------------------------------------------------------
-- acdh:hasBinarySize is now applicable only for acdh:RepoObject
--------------------------------------------------------------------------------
create temporary table _repoobjects as select id, 'Collection' as type from metadata where property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and value in ('https://vocabs.acdh.oeaw.ac.at/schema#Resource', 'https://vocabs.acdh.oeaw.ac.at/schema#Image', 'https://vocabs.acdh.oeaw.ac.at/schema#Metadata', 'https://vocabs.acdh.oeaw.ac.at/schema#Collection', 'https://vocabs.acdh.oeaw.ac.at/schema#BinaryContent');
delete from metadata m where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasBinarySize' and not exists (select 1 from _repoobjects where m.id = id);

select count(*) from relations where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasAccessRestriction';
select count(*) from metadata where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasNumberOfItems';
select count(*) from metadata where property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasBinarySize';

--------------------------------------------------------------------------------
-- compute hasAccessRestrictionSummary and hasLicenseSummary for collections
--------------------------------------------------------------------------------
DROP TABLE IF EXISTS _collections;
CREATE TEMPORARY TABLE _collections AS
    SELECT id FROM metadata WHERE property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' AND substring(value, 1, 1000) = 'https://vocabs.acdh.oeaw.ac.at/schema#Collection';
DROP TABLE IF EXISTS _children;
CREATE TEMPORARY TABLE _children AS
    SELECT id AS cid, (get_relatives(id, 'https://vocabs.acdh.oeaw.ac.at/schema#isPartOf', 0)).* FROM _collections;
DROP TABLE IF EXISTS _aggregates;
CREATE TEMPORARY TABLE _aggregates AS
    SELECT 
      id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasAccessRestrictionSummary' AS property, lang, 
      string_agg(value || ' ' || count, E'\\n' ORDER BY count DESC, value) AS value
    FROM (
        SELECT cid AS id, rl.property, lang, value, count(*) AS count
        FROM
            _children r
            JOIN relations rl ON r.id = rl.id AND rl.property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasAccessRestriction'
            JOIN metadata m ON rl.target_id = m.id AND m.property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasTitle'
        GROUP BY 1, 2, 3, 4
    ) a1
    GROUP BY 1, 2, 3;
INSERT INTO _aggregates
    SELECT 
      id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicenseSummary' AS property, lang, 
      string_agg(value || ' ' || count, E'\\n' ORDER BY count DESC, value) AS value
    FROM (
        SELECT cid AS id, rl.property, lang, value, count(*) AS count
        FROM
            _children r
            JOIN relations rl ON r.id = rl.id AND rl.property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicense'
            JOIN metadata m ON rl.target_id = m.id AND m.property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasTitle'
        GROUP BY 1, 2, 3, 4
    ) a1
    GROUP BY 1, 2, 3;
-- for empty collections
INSERT INTO _aggregates 
    SELECT id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasAccessRestrictionSummary', 'en', '' 
    FROM _collections c 
    WHERE NOT EXISTS (SELECT 1 FROM _aggregates WHERE c.id = id);
INSERT INTO _aggregates 
    SELECT id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicenseSummary', 'en', '' 
    FROM _collections c 
    WHERE NOT EXISTS (SELECT 1 FROM _aggregates WHERE c.id = id);
-- insert
DELETE FROM metadata WHERE property IN ('https://vocabs.acdh.oeaw.ac.at/schema#hasLicenseSummary', 'https://vocabs.acdh.oeaw.ac.at/schema#hasAccessRestrictionSummary');
INSERT INTO metadata (mid, id, property, type, lang, value)
    SELECT nextval('mid_seq'), id, property, 'http://www.w3.org/2001/XMLSchema#string', lang, value FROM _aggregates;

--------------------------------------------------------------------------------
-- object/datatype property mismatch
--------------------------------------------------------------------------------
-- object being datatype
select m2.* from metadata m1 join identifiers i using (id) join metadata m2 on i.ids = m2.property where m1.property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and m1.value = 'http://www.w3.org/2002/07/owl#ObjectProperty';
-- datatype being object
select m2.* from metadata m1 join identifiers i using (id) join relations m2 on i.ids = m2.property where m1.property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and m1.value = 'http://www.w3.org/2002/07/owl#DatatypeProperty';
select m2.* from metadata m1 join identifiers i using (id) join metadata m2 on i.ids = m2.property where m1.property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and m1.value = 'http://www.w3.org/2002/07/owl#DatatypeProperty' and m2.type = 'URI';

--------------------------------------------------------------------------------
-- commit changes
--------------------------------------------------------------------------------
COMMIT;
