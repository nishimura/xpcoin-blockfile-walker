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
