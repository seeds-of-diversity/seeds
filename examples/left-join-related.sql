# How to get a related column in a left-joined row selected from a group by a different column
#
# People can have 0 or more pets.
# Find the favourite food of each person's oldest pet.

create table test1people (
    name text
);

create table test1pets (
    name  text,
    owner text,
    age   integer,
    food  text
);

insert into test1people values ('John'),('Bob'),('Mary');
insert into test1pets values ('Fido','John',5,'popcorn'),
                             ('Roger','Bob',2,'chicken'),
                             ('Gofer','Bob',3,'fish');

# This partitions pets by owner, sorts each partition by age desc, and puts row 1 of each partition in a table,
# then left joins people against that table.
#
# Result should be
#     John     Fido     5     popcorn       (John only has one pet)
#     Bob      Gofer    3     fish          (Bob's oldest pet)
#     Mary     null     null  null          (Mary doesn't have a pet)
#
select * from test1people left join (
    select * from (
        select *, row_number() over (partition by owner order by age desc) as row_num from test1pets
    ) as ordered_pets
    where ordered_pets.row_num = 1
) as oldest_pets
on test1people.name = oldest_pets.owner;

