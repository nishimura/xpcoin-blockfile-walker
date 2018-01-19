CREATE INDEX bindex_height on bindex(height);

CREATE INDEX txindex_height_btree on txindex (height);
CREATE INDEX txindex_outaddr_gin on txindex using GIN (outaddr);

CREATE INDEX addr_hash on addr(hash);
CREATE INDEX addr_hash_blockheight on addr(hash, blockheight);
