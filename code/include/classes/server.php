<?php
  // +-------------------------------------------------------------------+
  // | Appleseed Web Community Management Software                       |
  // | http://appleseed.sourceforge.net                                  |
  // +-------------------------------------------------------------------+
  // | FILE: server.php                              CREATED: 04-24-2007 + 
  // | LOCATION: /code/include/classes/             MODIFIED: 04-24-2007 +
  // +-------------------------------------------------------------------+
  // | Copyright (c) 2004-2007 Appleseed Project                         |
  // +-------------------------------------------------------------------+
  // | This program is free software; you can redistribute it and/or     |
  // | modify it under the terms of the GNU General Public License       |
  // | as published by the Free Software Foundation; either version 2    |
  // | of the License, or (at your option) any later version.            |
  // |                                                                   |
  // | This program is distributed in the hope that it will be useful,   |
  // | but WITHOUT ANY WARRANTY; without even the implied warranty of    |
  // | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the     |
  // | GNU General Public License for more details.                      |	
  // |                                                                   |
  // | You should have received a copy of the GNU General Public License |
  // | along with this program; if not, write to:                        |
  // |                                                                   |
  // |   The Free Software Foundation, Inc.                              |
  // |   59 Temple Place - Suite 330,                                    | 
  // |   Boston, MA  02111-1307, USA.                                    |
  // |                                                                   |
  // |   http://www.gnu.org/copyleft/gpl.html                            |
  // +-------------------------------------------------------------------+
  // | AUTHORS: Michael Chisari <michael.chisari@gmail.com>              |
  // +-------------------------------------------------------------------+
  // | VERSION:      0.7.0                                               |
  // | DESCRIPTION.  Light-weight server class definitions.              |
  // +-------------------------------------------------------------------+

  require_once ("code/include/classes/BASE/remote.php");
  require_once ("code/include/classes/BASE/xml.php");
  
  // Light-weight server class for node communication.
  class cSERVER {
    
    var $SiteURL;
    var $SiteDomain;
    var $Database;
    var $DatabaseUsername;
    var $DatabaseHost;
    var $DatabasePassword;
    var $DatabaseLink;
    var $TablePrefix;
    var $Token;
    var $Token_Uid;
    var $ReturnUsername;

    function cSERVER ($pHOST) {
      
      // Create XML object.
      $this->XML = new cXML ();
      
      // Create REMOTE object.
      $this->REMOTE = new cREMOTE ($pHOST);
      
      // Initialize Variables.
      $this->SiteURL = null;
      $this->SiteDomain = null;
      $this->Database = null;
      $this->DatabaseHost = null;
      $this->DatabaseUsername = null;
      $this->DatabasePassword = null;
      $this->DatabaseLink = null;
      $this->TablePrefix = null;
      $this->Token = null;
      $this->Token_uID = null;
      $this->ReturnUsername = null;
      
      // Load Site Information.
      $this->LoadSiteInfo ();
      
      // Connect To The Database.
      $this->Connect ();
      
      return (TRUE);
    } // Constructor
    
    function LoadSiteInfo () {
      $settings = file ("data/site.adat"); 
      
      // Initialize Variables.
      $setting_un = null;
      $setting_pw = null;
      $setting_db = null;
      $setting_pre = null;
      $setting_host = null;
      $setting_ver = null;
      
      foreach ($settings as $setting) {
        
        // Split the line into two parts, type and ethod.
        list ($settingtype, $settingmethod) = split (":", $setting,2);
  
        // Create a php variable using the resulting data.
        $settingidentifier = 'setting_' . $settingtype;
        $$settingidentifier = rtrim (ltrim ($settingmethod));
  
      } // foreach 
      
      global $gAPPLESEEDVERSION;
      $gAPPLESEEDVERSION = $setting_ver;
      
      $this->Database = $setting_db;
      $this->DatabaseUsername = $setting_un;
      $this->DatabasePassword = $setting_pw;
      $this->DatabaseHost = $setting_host;
      $this->TablePrefix = $setting_pre;
      $this->SiteURL = $setting_url;
      $this->SiteDomain = str_replace ("http://", "", $setting_url);
      $this->SiteDomain = str_replace ("/", "", $this->SiteDomain);
      
      return (TRUE);
    } // LoadSiteInfo
    
    function Connect () {
      
      if (!$this->DatabaseLink = mysql_pconnect ($this->DatabaseHost, $this->DatabaseUsername, $this->DatabasePassword))
        return (FALSE);
        
      if (!mysql_select_db ($this->Database, $this->DatabaseLink))
        return (FALSE);
      
      return (TRUE);
    } // Connect
    
    function CheckLocalToken ($pTOKEN, $pDOMAIN) {
      
      // Check our local database, see if this token exists.
      $sql_statement = "
        SELECT userAuth_uID 
        FROM   " . $this->TablePrefix . "userTokens
        WHERE  Token = '%s'
        AND    Domain = '%s'
        AND    Stamp > DATE_ADD(now(), INTERVAL -30 MINUTE) 
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pTOKEN),
                                mysql_real_escape_string ($pDOMAIN));
                                
      $sql_result = mysql_query ($sql_statement);
      
      // Check if we got a result row.
      $result_count = mysql_num_rows ($sql_result);
      
      if ($result_count == 0) {
        // No results found.  Unauthenticated. Send back an error.
        $this->XML->ErrorData ("ERROR.NOTFOUND");
        return (FALSE);
      } else {
        // Load and send back the username.
        global $gUSERNAME;
        $result = mysql_fetch_assoc ($sql_result);
        $uid = $result['userAuth_uID'];
        mysql_free_result ($sql_result);
        
        $sql_statement = "SELECT Username
                          FROM   " . $this->TablePrefix . "userAuthorization
                          WHERE uID = $uid
        ";
        $sql_result = mysql_query($sql_statement);
        $result = mysql_fetch_assoc ($sql_result);
        $gUSERNAME = $result['Username'];
        
        // Load and send back the fullname.
        global $gFULLNAME;
        
        $sql_statement = "SELECT Fullname,Alias
                          FROM   " . $this->TablePrefix . "userProfile
                          WHERE userAuth_uID = $uid
        ";
        $sql_result = mysql_query($sql_statement);
        $result = mysql_fetch_assoc ($sql_result);
        $gFULLNAME = $result['Fullname'];
        if ($result['Alias']) $gFULLNAME = $result['Alias'];
        
        $this->XML->Load ("code/include/data/xml/check_token.xml");
        mysql_free_result ($sql_result);
      } // if
      
      return (TRUE);
    } // CheckLocalToken
    
    function CheckRemoteToken ($pTOKEN, $pDOMAIN) {
      
      // First, check our remote cache database, see if this token exists.
      $sql_statement = "
        SELECT Username 
        FROM   " . $this->TablePrefix . "authTokens
        WHERE  Token = '%s'
        AND    Domain = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pTOKEN),
                                mysql_real_escape_string ($pDOMAIN));
                                
      $sql_result = mysql_query ($sql_statement);
      
      // Check if we got a result row.
      $result_count = mysql_num_rows ($sql_result);
      
      // Check if a token record was found.
      if ($result_count == 0) {
        // If not, send back to see if Token is valid.
        $REMOTE = new cREMOTE ($pDOMAIN);
        $datalist = array ("gACTION"   => "CHECK_TOKEN",
                           "gTOKEN"    => $pTOKEN,
                           "gDOMAIN"   => $this->SiteDomain);
        $REMOTE->Post ($datalist, 1);

        $this->XML->Parse ($REMOTE->Return);

        // If no appleseed version was retrieved, an invalid url was used.
        $version = ucwords ($this->XML->GetValue ("version", 0));
        if (!$version) return (FALSE);
        
        $result = $this->XML->GetValue ("result", 0);
        $username = $this->XML->GetValue ("username", 0);
        $fullname = $this->XML->GetValue ("fullname", 0);
        
        // Check if token is valid.
        if ($result) {
          // If so, store for later use.
          
          $this->StoreToken($pTOKEN, $username, $pDOMAIN);
          
          $this->ReturnUsername = $username;
          $this->ReturnFullname = $fullname;
        } else {
          // If not, send an error.
          return (FALSE);
        } // if
      } else {
        // A result was found, use this result.
        $result = mysql_fetch_assoc ($sql_result);
        mysql_free_result ($sql_result);
        
        $this->ReturnUsername = $result['Username'];
        $this->Token = $pTOKEN;
      } // if
      
      return (TRUE);
    } // CheckRemoteToken
    
    function StoreToken ($pTOKEN, $pUSERNAME, $pDOMAIN) {
      
      // Delete all existing tokens.
      $sql_statement = "
        DELETE FROM " . $this->TablePrefix . "authTokens
        WHERE Username = '%s'
        AND   Domain   = '%s'

      "; 
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME),
                                mysql_real_escape_string ($pDOMAIN));
                                
      $sql_result = mysql_query ($sql_statement);
      
      // Insert new token.
      $sql_statement = "
        INSERT INTO " . $this->TablePrefix . "authTokens
        (Username, Domain, Address, Host, Token, Stamp)
        VALUES ('%s', '%s', '%s', '%s', '%s', NOW());
      "; 
      
      $address = $_SERVER['REMOTE_ADDR'];
      $host = gethostbyaddr ($address);
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME),
                                mysql_real_escape_string ($pDOMAIN),
                                mysql_real_escape_string ($address),
                                mysql_real_escape_string ($host),
                                mysql_real_escape_string ($pTOKEN));
                                
      $sql_result = mysql_query ($sql_statement);
      
      return (TRUE);
    } // StoreToken
    
    function AddFriendRequest ($pTOKEN, $pUSERNAME, $pDOMAIN) {
      
      $this->CheckRemoteToken ($pTOKEN, $pDOMAIN); 
      
      $userAuth = $this->TablePrefix . "userAuthorization";
      $friendInfo = $this->TablePrefix . "friendInformation";
      
      $friendInfo = $this->TablePrefix . "friendInformation";
      $userProfile = $this->TablePrefix . "userProfile";
      $userAuth = $this->TablePrefix . "userAuthorization";
      
      // Select the User ID
      $sql_statement = "
        SELECT $userAuth.uID, $userProfile.Fullname, $userProfile.Alias
        FROM   $userAuth,$userProfile
        WHERE  $userAuth.Username = '%s'
        AND    $userProfile.userAuth_uID = $userAuth.uID
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME)
                                );
      
      $sql_result = mysql_query ($sql_statement);
      $result = mysql_fetch_assoc ($sql_result);
      global $gFULLNAME;
      $gFULLNAME = $result['Fullname'];
      if ($result['Alias']) $gFULLNAME = $result['Alias'];
      $uID = $result['uID'];
      mysql_free_result ($sql_result);
      
      // Delete any current records.
      $sql_statement = "
        DELETE FROM $friendInfo
        WHERE userAuth_uID = %s
        AND Username = '%s'
        AND Domain = '%s'
      ";      
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($uID),
                                mysql_real_escape_string ($pUSERNAME),
                                mysql_real_escape_string ($pDOMAIN)
                                );
                                
      $sql_result = mysql_query ($sql_statement);
      mysql_free_result ($sql_result);
      
      // Select the max sort ID
      $sql_statement = "
        SELECT MAX(sID)
        FROM   $friendInfo
        WHERE    userAuth_uID = %s 
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($uID)
                                );
      
      $sql_result = mysql_query ($sql_statement);
      $result = mysql_fetch_assoc ($sql_result);
      $sID = $result['sID'];
      if ($sID) 
        $sID++;
      else
        $sID = 1;
      mysql_free_result ($sql_result);
      
      // Insert the friend record.
      $sql_statement = "
         INSERT INTO $friendInfo
         (userAuth_uID, sID, Username, Domain, Verification, Stamp)
         VALUES
         (%s, %s, '%s', '%s', 2, NOW())
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($uID),
                                mysql_real_escape_string ($sID),
                                mysql_real_escape_string ($this->ReturnUsername),
                                mysql_real_escape_string ($pDOMAIN)
                                );
                                
      $sql_result = mysql_query($sql_statement);
      
      mysql_free_result ($sql_result);
      
      global $gRESULT;
      $gRESULT = 1;
                                
      // Load and send back the fullname.
      global $gFULLNAME;
      
      $sql_statement = "
        SELECT Fullname, Alias
        FROM   $userProfile,$userAuth
        WHERE  $userProfile.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME));
                                
      $sql_result = mysql_query($sql_statement);
      $result = mysql_fetch_assoc ($sql_result);
      $gFULLNAME = $result['Fullname'];
      if ($result['Alias']) $gFULLNAME = $result['Alias'];
      mysql_free_result ($sql_result);
      
      $this->XML->Load ("code/include/data/xml/add_friend_request.xml");
      
      return (TRUE);
    } // AddFriendRequest
    
    function DeleteFriend ($pTOKEN, $pUSERNAME, $pDOMAIN) {
      
      $this->CheckRemoteToken ($pTOKEN, $pDOMAIN); 
      
      $userAuth = $this->TablePrefix . "userAuthorization";
      $friendInfo = $this->TablePrefix . "friendInformation";
      
      // Delete the friend record.
      
      $friendInfo = $this->TablePrefix . "friendInformation";
      $userProfile = $this->TablePrefix . "userProfile";
      $userAuth = $this->TablePrefix . "userAuthorization";
      
      $sql_statement = "
        DELETE $friendInfo
        FROM   $friendInfo,$userAuth
        WHERE  $friendInfo.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
        AND    $friendInfo.Username = '%s'
        AND    $friendInfo.Domain = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME),
                                mysql_real_escape_string ($this->ReturnUsername),
                                mysql_real_escape_string ($pDOMAIN)
                                );
                                
      $sql_result = mysql_query($sql_statement);
      global $gRESULT;
      $gRESULT = 1;
                                
      // Load and send back the fullname.
      global $gFULLNAME;
      
      $sql_statement = "
        SELECT Fullname,Alias
        FROM   $userProfile,$userAuth
        WHERE  $userProfile.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME));
                                
      $sql_result = mysql_query($sql_statement);
      $result = mysql_fetch_assoc ($sql_result);
      $gFULLNAME = $result['Fullname'];
      if ($result['Alias']) $gFULLNAME = $result['Alias'];
      mysql_free_result ($sql_result);
      
      $this->XML->Load ("code/include/data/xml/delete_friend.xml");
      
      return (TRUE);
    } // DeleteFriend
    
    function CancelFriendRequest ($pTOKEN, $pUSERNAME, $pDOMAIN) {
      
      $this->CheckRemoteToken ($pTOKEN, $pDOMAIN); 
      
      $userAuth = $this->TablePrefix . "userAuthorization";
      $friendInfo = $this->TablePrefix . "friendInformation";
      
      // Update the friend record.
      
      $friendInfo = $this->TablePrefix . "friendInformation";
      $userProfile = $this->TablePrefix . "userProfile";
      $userAuth = $this->TablePrefix . "userAuthorization";
      
      $sql_statement = "
        UPDATE $friendInfo,$userAuth
        SET    $friendInfo.Verification = 1
        WHERE  $friendInfo.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
        AND    $friendInfo.Username = '%s'
        AND    $friendInfo.Domain = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME),
                                mysql_real_escape_string ($this->ReturnUsername),
                                mysql_real_escape_string ($pDOMAIN)
                                );
                                
      $sql_result = mysql_query($sql_statement);
      global $gRESULT;
      
      $gRESULT = 1;
                                
      // Load and send back the fullname.
      global $gFULLNAME;
      
      $sql_statement = "
        SELECT Fullname,Alias
        FROM   $userProfile,$userAuth
        WHERE  $userProfile.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME));
                                
      $sql_result = mysql_query($sql_statement);
      $result = mysql_fetch_assoc ($sql_result);
      $gFULLNAME = $result['Fullname'];
      if ($result['Alias']) $gFULLNAME = $result['Alias'];
      mysql_free_result ($sql_result);
      
      $this->XML->Load ("code/include/data/xml/cancel_friend_request.xml");
      
      return (TRUE);
    } // CancelFriendRequest
    
    function ApproveFriendRequest ($pTOKEN, $pUSERNAME, $pDOMAIN) {
      
      $this->CheckRemoteToken ($pTOKEN, $pDOMAIN); 
      
      $userAuth = $this->TablePrefix . "userAuthorization";
      $friendInfo = $this->TablePrefix . "friendInformation";
      
      // Update the friend record.
      
      $friendInfo = $this->TablePrefix . "friendInformation";
      $userProfile = $this->TablePrefix . "userProfile";
      $userAuth = $this->TablePrefix . "userAuthorization";
      
      $sql_statement = "
        UPDATE $friendInfo,$userAuth
        SET    $friendInfo.Verification = 1
        WHERE  $friendInfo.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
        AND    $friendInfo.Username = '%s'
        AND    $friendInfo.Domain = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME),
                                mysql_real_escape_string ($this->ReturnUsername),
                                mysql_real_escape_string ($pDOMAIN)
                                );
                                
      $sql_result = mysql_query($sql_statement);
      global $gRESULT;
      
      $gRESULT = 1;
                                
      // Load and send back the fullname.
      global $gFULLNAME;
      
      $sql_statement = "
        SELECT Fullname,Alias
        FROM   $userProfile,$userAuth
        WHERE  $userProfile.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME));
                                
      $sql_result = mysql_query($sql_statement);
      $result = mysql_fetch_assoc ($sql_result);
      $gFULLNAME = $result['Fullname'];
      if ($result['Alias']) $gFULLNAME = $result['Alias'];
      mysql_free_result ($sql_result);
      
      $this->XML->Load ("code/include/data/xml/approve_friend_request.xml");
      
      return (TRUE);
    } // ApproveFriendRequest
    
    function CheckFriendStatus ($pTOKEN, $pUSERNAME, $pDOMAIN) {
      
      $this->CheckRemoteToken ($pTOKEN, $pDOMAIN); 
      
      $userAuth = $this->TablePrefix . "userAuthorization";
      $friendInfo = $this->TablePrefix . "friendInformation";
      
      $sql_statement = "
        SELECT $friendInfo.Verification AS Verification
        FROM $userAuth, $friendInfo 
        WHERE $friendInfo.userAuth_uID = $userAuth.uID 
        AND $userAuth.Username='%s'
        AND $friendInfo.Username='%s' 
        AND $friendInfo.Domain='%s';
      ";
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME),
                                mysql_real_escape_string ($this->ReturnUsername),
                                mysql_real_escape_string ($pDOMAIN)
                                );
                                
      $sql_result = mysql_query ($sql_statement);
      
      $result = mysql_fetch_assoc ($sql_result);
      
      global $gFRIENDSTATUS;
      $gFRIENDSTATUS = $result['Verification'];
      
      $this->XML->Load ("code/include/data/xml/check_friend_status.xml");
      
      return (TRUE);
    } // CheckFriendStatus
    
    function GetUserInformation ($pUSERNAME) {
      
      // Load and send back the fullname.
      global $gFULLNAME;
      
      $userProfile = $this->TablePrefix . "userProfile";
      $userInfo = $this->TablePrefix . "userInformation";
      $userAuth = $this->TablePrefix . "userAuthorization";
      
      $sql_statement = "
        SELECT Fullname, Alias
        FROM   $userProfile,$userAuth
        WHERE  $userProfile.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME));
                                
      $sql_result = mysql_query($sql_statement);
      $result = mysql_fetch_assoc ($sql_result);
      $gFULLNAME = $result['Fullname'];
      if ($result['Alias']) $gFULLNAME = $result['Alias'];
      mysql_free_result ($sql_result);
      
      // Load and send back the online status.
      $sql_statement = "
        SELECT OnlineStamp
        FROM   $userInfo,$userAuth
        WHERE  $userInfo.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME));
                                
      $sql_result = mysql_query($sql_statement);
      $result = mysql_fetch_assoc ($sql_result);
      mysql_free_result ($sql_result);
      
      $currently = strtotime ("now");
      $online = strtotime ($result['OnlineStamp']);

      $difference = $currently - $online;
      
      global $gONLINE;
      $gONLINE = NULL;
      if ($difference < 180) $gONLINE = "ONLINE";
            
      $this->XML->Load ("code/include/data/xml/get_user_information.xml");
      
      return (TRUE);
    } // GetUserInformation
    
    function CheckLogin ($pUSERNAME, $pDOMAIN) {
      
      $authVerification = $this->TablePrefix . "authVerification";
      $userProfile = $this->TablePrefix . "userProfile";
      $userAuth = $this->TablePrefix . "userAuthorization";
      
      // Check our local database, see if this token exists.
      $sql_statement = "
        SELECT $authVerification.*,$userProfile.Fullname,$userProfile.Alias
        FROM   $authVerification, $userProfile,$userAuth
        WHERE  $authVerification.Username = '%s'
        AND    $authVerification.Domain = '%s'
        AND    $userProfile.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME),
                                mysql_real_escape_string ($pDOMAIN),
                                mysql_real_escape_string ($pUSERNAME));
                                
      $sql_result = mysql_query ($sql_statement);

      // Check if we got a result row.
      $result_count = mysql_num_rows ($sql_result);

      if ($result_count) {
        $result = mysql_fetch_assoc ($sql_result);
        
        global $gUSERNAME, $gFULLNAME, $gTIME, $gDOMAIN;
        global $gADDRESS, $gHOST;
        $gUSERNAME = $pUSERNAME;
        $gFULLNAME = $result['Fullname'];
        if ($result['Alias']) $gFULLNAME = $result['Alias'];
        $gTIME = time ();
        $gDOMAIN = str_replace ("www.", NULL, $_SERVER['HTTP_HOST']);
        $gADDRESS = $result['Address'];
        $gHOST = $result['Host'];
        $tID = $result['tID'];
        
        // Set the Verification To Inactive
        $sql_statement = "
          UPDATE $authVerification
          SET Active = 0
          WHERE tID = %s
        "; 
        
        $sql_statement = sprintf ($sql_statement,
                                  mysql_real_escape_string ($tID));
                                  
        $sql_result = mysql_query ($sql_statement);
        
        mysql_free_result ($sql_result);
        
      } else {
        // No results found.  Unauthenticated. Send back an error.
        $this->XML->ErrorData ("ERROR.UNVERIFIED");
        return (FALSE);
      } // if
      
      $this->XML->Load ("code/include/data/xml/check_login.xml");
      
      return (TRUE);
    } // CheckLogin
    
    function GetIconList ($pUSERNAME) {
      
      $userIcons = $this->TablePrefix . "userIcons";
      $userAuth = $this->TablePrefix . "userAuthorization";
      $finaldata = null;
      
      $this->XML->Load ("code/include/data/xml/get_icon_list/top.xml");
      $finaldata = $this->XML->Data;
      
      // Check our local database, see if this token exists.
      $sql_statement = "
        SELECT $userIcons.Filename,$userIcons.Keyword
        FROM   $userIcons,$userAuth
        WHERE  $userIcons.userAuth_uID = $userAuth.uID
        AND    $userAuth.Username = '%s'
      ";
      
      $sql_statement = sprintf ($sql_statement,
                                mysql_real_escape_string ($pUSERNAME),
                                mysql_real_escape_string ($pDOMAIN),
                                mysql_real_escape_string ($pUSERNAME));
                                
      $sql_result = mysql_query ($sql_statement);

      // Check if we got a result row.
      $result_count = mysql_num_rows ($sql_result);
      
      global $gFILENAME, $gKEYWORD;
      
      // Loop through the results.
      while ($result = mysql_fetch_assoc($sql_result)) {
        $gFILENAME = $result['Filename'];
        $gKEYWORD = $result['Keyword'];
        $this->XML->Load ("code/include/data/xml/get_icon_list/middle.xml");
        $finaldata .= $this->XML->Data;
      } // while
      
      $this->XML->Load ("code/include/data/xml/get_icon_list/bottom.xml");
      $finaldata .= $this->XML->Data;
      
      $this->XML->Data = $finaldata;
      
      return (TRUE);
    } // GetIconList
    
  } // cSERVER
