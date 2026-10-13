-- One-time setup, run as sahladmin (password login) in the `sahl` database, after 01-roles.sql:
--   docs/setup/08-postgres-entra-sign-in.md, step 2
-- Moves every table sahladmin created to sahl_owner. Only a table's owner may give it away, hence sahladmin.
\set ON_ERROR_STOP on

do $$
declare
    t record;
begin
    for t in select tablename from pg_tables where schemaname = 'public' and tableowner = 'sahladmin' loop
        -- Also moves the table's own sequences (the id columns).
        execute format('alter table public.%I owner to sahl_owner', t.tablename);
    end loop;
end $$;

-- From now on sahladmin's sessions in `sahl` act as sahl_owner too.
alter role sahladmin in database sahl set role = 'sahl_owner';

-- Check: everything in `public` should now belong to sahl_owner.
select case c.relkind when 'r' then 'table' when 'S' then 'sequence' when 'i' then 'index' else c.relkind::text end as kind,
       pg_get_userbyid(c.relowner) as owner,
       count(*)
from pg_class c join pg_namespace n on n.oid = c.relnamespace
where n.nspname = 'public'
group by 1, 2
order by 1, 2;
