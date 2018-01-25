CREATE INDEX txindex_bhash_btree on txindex (bhash);

CREATE INDEX txindex_outdata_addr_gin on txindex using gin(substrbytea(outdata, 1, 8));
CREATE INDEX txindex_outdata_next_gin on txindex using gin(substrbytea(outdata, 1, 9));
