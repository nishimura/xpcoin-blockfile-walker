CREATE INDEX bindex_height_btree on bindex(height);

CREATE INDEX txindex_height_btree on txindex (height);
CREATE INDEX txindex_inaddr_gin on txindex using GIN (inaddr);
CREATE INDEX txindex_outaddr_gin on txindex using GIN (outaddr);
