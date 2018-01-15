CREATE TABLE posinfo(
  nfile int not null,
  npos int not null,
  nheight int not null
);

CREATE TABLE bindex(
  hash int not null primary key,
  height int not null
);

CREATE TABLE addr(
  hash int not null,
  blockheight int not null,
  intx int,
  outtx int
);
