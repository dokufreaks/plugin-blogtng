CREATE TABLE opts (opt,val);
CREATE UNIQUE INDEX idx_opt ON opts(opt);
INSERT INTO opts (opt,val) VALUES('dbversion',1);

CREATE TABLE entries (
    pid PRIMARY KEY,
    page,
    title,
    image,
    created INTEGER,
    lastmod INTEGER,
    author,
    login,
    email
);
CREATE UNIQUE INDEX idx_pid ON entries(pid);
CREATE INDEX idx_title ON entries(title);
CREATE INDEX idx_created ON entries(created);


