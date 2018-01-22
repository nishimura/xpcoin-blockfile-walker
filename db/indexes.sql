CREATE INDEX bindex_height_btree on bindex(height);


-- outdata
--
-- 1 - 8: nextaddr
-- 9 - 9: unspent: 0x01, spent: 0x02
-- 10 - 17: out value
-- 18 - 25: next tx hash
-- 26 - 29: next tx vin.n

CREATE FUNCTION substrbytea(bytea[], int, int) RETURNS bytea[] IMMUTABLE AS $$
  SELECT
    array_agg(substring(i from $2 for $3))
  FROM
    unnest($1) AS t(i)
$$ LANGUAGE SQL;

CREATE FUNCTION hex_to_int(hexval bytea) RETURNS bigint IMMUTABLE AS $$
DECLARE
    result bigint;
BEGIN
    EXECUTE 'SELECT x''' || encode(hexval, 'hex') || '''::bigint' INTO result; RETURN result;
END
$$ LANGUAGE plpgsql;


CREATE FUNCTION bytea_to_amount(bytea[], bytea) RETURNS numeric IMMUTABLE AS $$
  SELECT
      sum(hex_to_int(substring(i from 10 for 8)))
  FROM
    unnest($1) AS t(i)
  WHERE
    substring(i from 1 for 9) = $2 || E'\\x01'
$$ LANGUAGE SQL;

create index txindex_outdata_addr_gin on txindex using gin(substrbytea(outdata, 1, 8));
create index txindex_outdata_next_gin on txindex using gin(substrbytea(outdata, 1, 9));
