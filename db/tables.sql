CREATE TABLE posinfo(
  nfile bigint not null,
  npos bigint not null
);

CREATE TABLE bindex(
  hash bigint not null primary key,
  nfile bigint not null,
  npos bigint not null,
  height bigint not null
);

CREATE TABLE addr(
  hash bigint not null,
  blockheight bigint not null,
  nfile bigint not null,
  npos bigint not null,
  intx bigint,
  outtx bigint
);
