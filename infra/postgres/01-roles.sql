-- One-time setup on the Azure server, run as YOU (the server's Entra admin), starting in the `postgres` database:
--   docs/setup/08-postgres-entra-sign-in.md, step 2
--
-- Result:
--   app-sahl-dev-sdc-vmi3r3   the web app's managed identity, as a Postgres login (no password: Entra tokens)
--   sahl_owner                a group role (no login) that will own every table in `sahl`
-- The app, sahladmin and you are members, and each of your sessions in `sahl` acts as sahl_owner,
-- so whoever runs a migration, the tables it creates belong to sahl_owner and everyone else can still change them.
\set ON_ERROR_STOP on

-- The pgaadauth_* functions only exist in the `postgres` database. The role they create is server-wide.
-- Args: role name, the identity's object ID (az webapp identity show ... --query principalId), 'service' = a managed
-- identity or app, isAdmin, isMfa.
select * from pgaadauth_create_principal_with_oid('app-sahl-dev-sdc-vmi3r3', '30582149-e3b9-4886-a741-ed08c128024f', 'service', false, false);

\connect sahl
select current_user as me \gset

create role sahl_owner nologin;

-- The `public` schema belongs to azure_pg_admin; tables can only be created in it with CREATE.
grant usage, create on schema public to sahl_owner;

grant sahl_owner to "app-sahl-dev-sdc-vmi3r3", sahladmin, :"me";

-- Every new session of these roles in `sahl` starts with SET ROLE sahl_owner.
-- (sahladmin sets its own default in 02-ownership.sql: only it may.)
alter role "app-sahl-dev-sdc-vmi3r3" in database sahl set role = 'sahl_owner';
alter role :"me" in database sahl set role = 'sahl_owner';
