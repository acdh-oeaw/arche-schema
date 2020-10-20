BEGIN;

--------------------------------------------------------------------------------
-- object/datatype property mismatch
--------------------------------------------------------------------------------
-- object being datatype
select m2.* from metadata m1 join identifiers i using (id) join metadata m2 on i.ids = m2.property where m1.property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and m1.value = 'http://www.w3.org/2002/07/owl#ObjectProperty';
-- datatype being object
select m2.* from metadata m1 join identifiers i using (id) join relations m2 on i.ids = m2.property where m1.property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and m1.value = 'http://www.w3.org/2002/07/owl#DatatypeProperty';
select m2.* from metadata m1 join identifiers i using (id) join metadata m2 on i.ids = m2.property where m1.property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' and m1.value = 'http://www.w3.org/2002/07/owl#DatatypeProperty' and m2.type = 'URI';

--------------------------------------------------------------------------------
-- removed inverse properties
--------------------------------------------------------------------------------
select * from relations where property in ('https://vocabs.acdh.oeaw.ac.at/schema#isContinuedBy', 'https://vocabs.acdh.oeaw.ac.at/schema#isDocumentedBy', 'https://vocabs.acdh.oeaw.ac.at/schema#isActorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isAuthorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isContactOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isContributorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isCoverageOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isCreatorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isCuratorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isDepositorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isDerivedPublicationOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isEditorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isFunderOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isHostingOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isLicensorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#hasMember', 'https://vocabs.acdh.oeaw.ac.at/schema#hasMetadata', 'https://vocabs.acdh.oeaw.ac.at/schema#isMetadataCreatorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isOwnerOf', 'https://vocabs.acdh.oeaw.ac.at/schema#hasPart', 'https://vocabs.acdh.oeaw.ac.at/schema#isPrincipalInvestigatorOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isRightsHolderOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isSourceOf', 'https://vocabs.acdh.oeaw.ac.at/schema#isSpatialCoverageOf', 'https://vocabs.acdh.oeaw.ac.at/schema#hasTitleImage', 'https://vocabs.acdh.oeaw.ac.at/schema#isPreviousVersionOf');

--------------------------------------------------------------------------------
-- properties in the acdh namespace not mentioned in the schema
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

begin;
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
rollback;

--------------------------------------------------------------------------------
-- compute hasAccessRestrictionSummary and hasLicenseSummary for collections
--------------------------------------------------------------------------------
CREATE TEMPORARY TABLE _collections AS
    SELECT id FROM metadata WHERE property = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' AND substring(value, 1, 1000) = 'https://vocabs.acdh.oeaw.ac.at/schema#Collection';
CREATE TEMPORARY TABLE _children AS
    SELECT id AS cid, (get_relatives(id, 'https://vocabs.acdh.oeaw.ac.at/schema#isPartOf', 0)).* FROM _collections;
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
INSERT INTO metadata (mid, id, property, type, lang, value)
    SELECT nextval('mid_seq'), id, property, 'http://www.w3.org/2001/XMLSchema#string', lang, value FROM _aggregates;


--------------------------------------------------------------------------------
-- commit changes
--------------------------------------------------------------------------------
COMMIT;
