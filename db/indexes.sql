CREATE INDEX bindex_npos on bindex(npos);
CREATE INDEX bindex_npos_nfile on bindex(npos, nfile);
CREATE INDEX bindex_height on bindex(height);

CREATE INDEX addr_hash on addr(hash);
CREATE INDEX addr_blockheight on addr(blockheight);
CREATE INDEX addr_npos on addr(npos);
CREATE INDEX addr_npos_nfile on addr(npos, nfile);
