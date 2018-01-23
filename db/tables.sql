DROP TABLE IF EXISTS txindex;
DROP TABLE IF EXISTS bindex;
DROP TABLE IF EXISTS posinfo;

CREATE TABLE posinfo(
  nfile bigint not null,
  npos bigint not null,
  height int not null
);

CREATE TABLE bindex(
  bhash bytea not null primary key,
  height int not null
);

CREATE TABLE txindex(
  txhash bytea not null,
  bhash bytea not null references bindex(bhash),

  outdata bytea[] not null
);
CREATE INDEX txindex_txhash_btree on txindex (txhash);


-- outdata
--
-- 1 - 8: nextaddr
-- 9 - 9: unspent: 0x01, spent: 0x02
-- 10 - 17: out value
-- 18 - 25: next tx hash
-- 26 - 29: next tx vin.n

DROP FUNCTION IF EXISTS substrbytea(bytea[], int, int);
CREATE FUNCTION substrbytea(bytea[], int, int) RETURNS bytea[] IMMUTABLE AS $$
  SELECT
    array_agg(substring(i from $2 for $3))
  FROM
    unnest($1) AS t(i)
$$ LANGUAGE SQL;

DROP FUNCTION IF EXISTS hex_to_int(hexval bytea);
CREATE FUNCTION hex_to_int(hexval bytea) RETURNS bigint IMMUTABLE AS $$
DECLARE
    result bigint;
BEGIN
    EXECUTE 'SELECT x''' || encode(hexval, 'hex') || '''::bigint' INTO result; RETURN result;
END
$$ LANGUAGE plpgsql;

DROP FUNCTION IF EXISTS bytea_to_amount(bytea[], bytea);
CREATE FUNCTION bytea_to_amount(bytea[], bytea) RETURNS numeric IMMUTABLE AS $$
  SELECT
      sum(hex_to_int(substring(i from 10 for 8)))
  FROM
    unnest($1) AS t(i)
  WHERE
    substring(i from 1 for 9) = $2 || E'\\x01'
$$ LANGUAGE SQL;
