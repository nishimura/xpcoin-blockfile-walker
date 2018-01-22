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

  outdata bytea[] not null
);
CREATE INDEX txindex_txhash_btree on txindex (txhash);

-- if txhash size is large:
--CREATE INDEX txindex_txhash_hash on txindex using hash (txhash);
