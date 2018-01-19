--
-- SQL EXAMPLE
--
insert into txindex values('aaa'::bytea, 1, ARRAY[]::bytea[], ARRAY['addr1'::bytea], ARRAY['next1'::bytea]);
insert into txindex values('aaa'::bytea, 2, ARRAY[]::bytea[], ARRAY['addr2'::bytea], ARRAY['next1'::bytea]);

insert into txindex values('bbb'::bytea, 3, ARRAY[]::bytea[], ARRAY['addr1'::bytea], ARRAY[]::bytea[]);

select * from txindex where outaddr @> ARRAY['addr1'::bytea] AND nexthash[array_position(outaddr, 'addr1'::bytea)] is null order by height desc;


--
-- REAL SAMPLE
--
-- height: 1797 - 1798 address
select * from txindex
where inaddr @> ARRAY[E'\\x6f4be8555a0aef70'::bytea]
   or outaddr @> ARRAY[E'\\x6f4be8555a0aef70'::bytea];

-- show only unspent
select * from txindex
where outaddr @> ARRAY[E'\\x7221ccf592f74746'::bytea]
  and nexthash[array_position(outaddr, E'\\x7221ccf592f74746'::bytea)] is null
order by height desc;
