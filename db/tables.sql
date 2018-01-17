CREATE TABLE posinfo(
  nfile bigint not null,
  npos bigint not null
);

CREATE TABLE bindex(
  hash bigint not null primary key,
  height bigint not null
);

CREATE TABLE txindex(
  hash bigint not null,
  txn int not null,
  nexthash bigint not null,
  nextn int not null,

  primary key (hash, txn)
);

CREATE TABLE addr(
  hash bigint not null,
  blockheight bigint not null,
  txid bigint not null,
  isin boolean not null
);
