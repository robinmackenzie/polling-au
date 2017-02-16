<?php
 
class dbService {

  const DSN = 'sqlite:au_polling_place_data.sqlite';
  
  public $db;
    
  public function __construct() {
    // db initialisation plus set error level
    $this->db = new PDO(self::DSN);
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }
  
  public function getPollingPlaceData() {
    
    $sql = 'SELECT 
    pp.polling_place_id,
    pp.polling_place_name,
    pp.status,
    st.state_name,
    div.division_name,
    pp.premises_name,
    pp.address1,
    pp.address2,
    pp.address3,
    pp.locality,
    pp.postcode,
    pp.lat,
    pp.long
    FROM 
    rpt_polling_place_dim pp
    INNER JOIN rpt_division_dim div 
    ON div.year = pp.year AND div.division_id = pp.division_id
    INNER JOIN rpt_state_dim st
    ON st.state_id = div.state_id
    WHERE
    pp.current_flag = 1';
    
    $query = $this->db->prepare($sql);
    $query->execute();
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    unset($query);
    return $rows;
    
  }
  
  public function getVotesByPollingPlace($id, $year) {
    
    $sql = 'SELECT 
    year,
    candidate_name,
    party_name,
    party_group_code,
    party_group_name,
    party_group_rgb,
    elected,
    first_time_elected,
    votes,
    swing,
    two_pp_votes,
    two_pp_swing
    FROM 
    rpt_polling_place_fact 
    WHERE 
    polling_place_id = :id AND year = :year';
    
    $query = $this->db->prepare($sql);
    $query->bindValue(':id', $id);
    $query->bindValue(':year', $year);
    $query->execute();
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    unset($query);
    return $rows;
    
  }
       
}





