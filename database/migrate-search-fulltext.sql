-- migrate-search-fulltext.sql - FULLTEXT index for article search.
USE earth_odyssey;

ALTER TABLE articles
  ADD FULLTEXT INDEX ft_articles_search (title, blurb, body);
