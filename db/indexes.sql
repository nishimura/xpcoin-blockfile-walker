CREATE INDEX bindex_height_btree on bindex(height);



create index txindex_outdata_addr_gin on txindex using gin(substrbytea(outdata, 1, 8));
create index txindex_outdata_next_gin on txindex using gin(substrbytea(outdata, 1, 9));
