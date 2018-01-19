DROP TABLE IF EXISTS txindex;
DROP TABLE IF EXISTS bindex;
DROP TABLE IF EXISTS posinfo;

CREATE TABLE posinfo(
  nfile bigint not null,
  npos bigint not null
);

CREATE TABLE bindex(
  bhash bytea not null,
  height int not null
);

CREATE TABLE txindex(
  txhash bytea not null,
  height int not null,

  inaddr bytea[] not null,
  outaddr bytea[] not null,
  nexthash bytea[] not null,
  nextn int[] not null
);
CREATE INDEX txindex_txhash_btree on txindex (txhash);


--
-- SQL EXAMPLE
--
-- insert into txindex values('aaa'::bytea, 1, ARRAY[]::bytea[], ARRAY['addr1'::bytea], ARRAY['next1'::bytea]);
-- insert into txindex values('aaa'::bytea, 2, ARRAY[]::bytea[], ARRAY['addr2'::bytea], ARRAY['next1'::bytea]);
--
-- insert into txindex values('bbb'::bytea, 3, ARRAY[]::bytea[], ARRAY['addr1'::bytea], ARRAY[]::bytea[]);

-- select * from txindex where outaddr @> ARRAY['addr1'::bytea] AND nexthash[array_position(outaddr, 'addr1'::bytea)] is null order by height desc;
