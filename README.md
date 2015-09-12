# alchemy news
This is just a test of alchemy api news.


CREATE TABLE news (
    id          BIGSERIAL PRIMARY KEY,
    alchemyid   text NOT NULL,
    original_timestamp timestamp,
    sentiment   text NOT NULL,
    url         text NOT NULL,
    title       text NOT NULL,
    doc         JSON
);
