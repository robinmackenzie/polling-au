-- rpt_party_group_code_dim
drop table if exists rpt_party_group_code_dim;

create table rpt_party_group_code_dim(
party_group_code text,
party_group_name text,
party_group_rgb text
);

insert into rpt_party_group_code_dim
select
  code as party_group_code,
  name as party_group_name,
  rgb as party_group_rgb
from ref_party_codes;

-- rpt_division_dim
drop table if exists rpt_division_dim;

create table rpt_division_dim (
NK text,
division_id text,
state_id text,
year text,
division_name text,
division_co text,
current_flag integer
);

insert into rpt_division_dim
select distinct
  p.DivId || "|" || Year AS NK,
  p.DivId division_id,
  p.StateCo state_id,
  p.Year year,
  p.DivName division_name,
  p.DivCo division_co,
  case when c.MaxYear = p.Year then 1 else 0 end current_flag
from raw_polling_places p
left join (
select max(p2.Year) MaxYear 
from raw_polling_places p2
) c on c.MaxYear = p.Year 
where PPId <> 0 

-- rpt_polling_place_dim
drop table if exists rpt_polling_place_dim;

create table rpt_polling_place_dim (
polling_place_id text,
polling_place_name text,
year text,
status text,
division_id text,
division_co text,
premises_name text,
address1 text,
address2 text,
address3 text,
locality text,
postcode text,
lat float,
long float,
current_flag int
);

insert into rpt_polling_place_dim
select distinct
  p.PPId,
  p.PPName,
  p.Year,
  p.Status,
  p.DivId,
  p.DivCo,
  p.PremisesName,
  p.Address1,
  p.Address2,
  p.Address3,
  p.Locality,
  p.Postcode,
  p.Lat,
  p.Long,
  case 
       when c.MaxYear = p.Year and p.Status = 'Current' then 1 
       else 0 
  end current_flag
from raw_polling_places p
left join (
select max(p2.Year) MaxYear 
from raw_polling_places p2
) c on c.MaxYear = p.Year
where p.PPId <> 0
order by p.PPId;

-- rpt_candidate_dim
drop table if exists rpt_candidate_dim;

create table rpt_candidate_dim (
NK text,
year text,
candidate_id text,
surname text,
given_name text,
full_name text,
current_flag int
);

insert into rpt_candidate_dim
select distinct
v.Year || "|" || v.CandidateID AS NK,
v.Year year,
v.CandidateID candidate_id,
v.Surname surname,
v.GivenNm given_name,
PROPER (v.GivenNm) || " " || PROPER(v.Surname) full_name,
case 
  when c.MaxYear = v.Year then 1 
  else 0 
end current_flag
from raw_votes_by_pp v
left join (
select max(v2.Year) MaxYear 
from raw_votes_by_pp v2
) c on c.MaxYear = v.Year
order by NK;

-- rpt_party_dim
drop table if exists rpt_party_dim;
create table rpt_party_dim (
year text,
party_name text,
party_group_code text
);

insert into rpt_party_dim
select distinct 
v.Year year,
case
    when v.PartyNm is null then v.PartyAb
    else v.PartyNm
end party_name,
case
    when v.PartyAb is null then "INF"
    else v.PartyAb 
end party_group_code
from raw_votes_by_pp v
order by 1, 2;

-- rpt_state_dim
drop table if exists rpt_state_dim;

create table rpt_state_dim (
state_id text,
state_name text
);

insert into rpt_state_dim
select distinct
  StateCo state_id,
  StateAb state_name
from raw_polling_places;

-- rpt_2016_polling_place_fact
drop table  rpt_2016_polling_place_fact;

create table rpt_2016_polling_place_fact (
year text,
state text,
division_id text,
division_name text,
polling_place_id text,
polling_place_name text,
candidate_id text,
candidate_name text,
party_name text,
party_group_code text,
party_group_name text,
party_group_rgb text,
elected int,
first_time_elected int,
votes int,
swing float,
two_pp_votes int,
two_pp_swing float,
ced_votes int,
ced_two_pp_votes int,
pp_ced_ratio float
);

insert into rpt_2016_polling_place_fact
select
v.Year year,
v.StateAb state,
v.DivisionID division_id,
v.DivisionNm division_name,
v.PollingPlaceID polling_place_id,
v.PollingPlace polling_place_name,
v.CandidateID candidate_id,
c.full_name candidate_name,
v.PartyNm party_name,
pg.party_group_code,
pg.party_group_name,
pg.party_group_rgb,
case
    when v.Elected = "Y" then 1
    else 0
end elected,
case
    when v.Elected = "Y" and v.HistoricElected = "N" then 1
    else 0
end first_time_elected,
cast(v.OrdinaryVotes as int) votes,
cast(v.Swing as float) swing,
cast(two.OrdinaryVotes as int) two_pp_votes,
cast(two.Swing as float) two_pp_swing,
cast(ced_votes.votes as int) ced_votes,
cast(ced_votes.two_pp_votes as int) ced_two_pp_votes,
cast(v.OrdinaryVotes / cast(ced_votes.votes as float) as float) pp_ced_ratio
from raw_votes_by_pp v
left join rpt_polling_place_dim pp on pp.polling_place_id = v.PollingPlaceID and pp.Year = v.Year
left join rpt_candidate_dim c on c.candidate_id = v.CandidateID and c.year = v.Year
left join rpt_party_group_code_dim pg on pg.party_group_code = v.PartyAb
left join raw_two_party_preferred_by_pp two on two.Year = v.Year 
and two.StateAb = v.StateAb 
and two.DivisionID = v.DivisionID 
and two.PollingPlaceID = v.PollingPlaceID
and two.CandidateID = v.CandidateID
inner join (
select
v2.Year,
v2.DivisionID,
v2.CandidateID,
sum(v2.OrdinaryVotes) votes,
sum(two2.OrdinaryVotes) two_pp_votes
from
raw_votes_by_pp v2
left join raw_two_party_preferred_by_pp two2 on two2.Year = v2.Year 
and two2.StateAb = v2.StateAb 
and two2.DivisionID = v2.DivisionID 
and two2.PollingPlaceID = v2.PollingPlaceID
and two2.CandidateID = v2.CandidateID
group by
v2.Year,
v2.DivisionID,
v2.CandidateID
) ced_votes on ced_votes.year = v.year
and ced_votes.DivisionID = v.DivisionID
and ced_votes.CandidateID = v.CandidateID
where v.year = '2016'
;