-- Migration 006: source language tag + user language preference filter
SET NAMES utf8mb4;

ALTER TABLE sources
    ADD COLUMN language VARCHAR(10) NULL DEFAULT NULL
    COMMENT 'ISO 639-1 language code (e.g. en, de, fr); NULL = unspecified'
    AFTER license;

ALTER TABLE users
    ADD COLUMN preferred_languages TEXT NULL DEFAULT NULL
    COMMENT 'JSON array of ISO 639-1 codes the user wants to see; NULL = all languages';
