CREATE INDEX bindex_height on bindex(height);

CREATE INDEX addr_hash on addr(hash);
CREATE INDEX addr_hash_blockheight on addr(hash, blockheight);
