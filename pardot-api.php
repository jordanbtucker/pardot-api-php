<?php
/**
 * A function-based PHP interface to the Pardot API version 3.
 */

/**
 * Assigns parameters to the global $pardot_config array.
 * @global array $pardot_config   The array that contains the default configuration for each call to the Pardot API.
 * @param string $email           The email address of the account used the login to the Pardot API.
 * @param string $password        The password of the account used the login to the Pardot API.
 * @param string $user_key        The user key of the account used the login to the Pardot API.
 * @return array                  The global $pardot_config array.
 */
function pardot_config($email, $password, $user_key) {
  global $pardot_config;
  
  $pardot_config = array(
      'email' => $email,
      'password' => $password,
      'user_key' => $user_key,
  );
  
  return $pardot_config;
}

/**
 * Internal use only.
 */
function pardot_request($object, $operator, $id_field, $id, $params) {
  $uri = 'https://pi.pardot.com/api/';
  $uri .= urlencode($object);
  $uri .= '/version/3';
  
  if(!empty($operator))
    $uri .= '/do/' . urlencode($operator);
  
  if(!empty($id_field) && !empty($id))
    $uri .= '/' . urlencode($id_field) . '/' . urlencode($id);
  
  $params ?: array();
  $params['format'] = 'json'; // Force JSON output instead of XML.
  $body = http_build_query($params);
  
  $request = curl_init($uri);
  curl_setopt($request, CURLOPT_POST, true); // Always use the POST method.
  curl_setopt($request, CURLOPT_POSTFIELDS , $body);
  curl_setopt($request, CURLOPT_RETURNTRANSFER, true); // Return the output instead of echoing it.
  
  $response = curl_exec($request);
  if($response === false) {
    $message = curl_error($request);
    $httpCode = curl_getinfo($request, CURLINFO_HTTP_CODE);
    
    curl_close($request);
    
    throw new Exception("Pardot API error for URI: $uri\nMessage: $message\nHTTP code: $httpCode");
  }
  
  curl_close($request);
  
  // Some API calls do not return a response, so return true instead.
  if(empty($response))
    return true;
  
  return json_decode($response, true);
}

/**
 * Internal use only.
 */
function pardot_login($config) {
  if(empty($config)) {
    global $pardot_config;
    $config = clone $pardot_config;
  }
  
  if(empty($config['email']))
    throw new Exception('Invalid config: `email` is required.');
  
  if(empty($config['password']))
    throw new Exception('Invalid config: `password` is required.');
  
  if(empty($config['user_key']))
    throw new Exception('Invalid config: `user_key` is required.');
  
  $object = 'login';
  $params = array(
      'email' => $config['email'],
      'password' => $config['password'],
      'user_key' => $config['user_key'],
  );
  
  $result = pardot_request($object, null, null, null, $params);
  
  if($result['@attributes']['stat'] === 'fail') {
    $message = $result['err'];
    throw new Exception("Pardot API error: $message");
  }
  
  $config['api_key'] = $result['api_key'];
}

/**
 * Internal use only.
 */
function pardot_auth_request($object, $operator, $id_field, $id, $params, $config) {
  if(empty($config)) {
    global $pardot_config;
    $config = clone $pardot_config;
  }
  
  if(empty($config['user_key']))
    throw new Exception('Invalid config: `user_key` is required.');

  $retrieved_api_key = false; // Flag to prevent calling pardot_login twice.
  
  if(empty($config['api_key'])) {
    pardot_login($config);
    $retrieved_api_key = true;
  }
  
  $params['user_key'] = $config['user_key'];
  $params['api_key'] = $config['api_key'];
  
  $result = pardot_request($object, $operator, $id_field, $id, $params);

  // If the call fails with 'Invalid API key or user key', call pardot_login and try again.
  if($result !== true && !$retrieved_api_key &&
      $result['@attributes']['stat'] === 'fail' &&
      $result['@attributes']['err_code'] === 1) {
    pardot_login($config);
    $result = pardot_request($object, $operator, $id_field, $id, $params);
  }
  
  if($result !== true && $result['@attributes']['stat'] === 'fail') {
    $message = $result['err'];
    throw new Exception("Pardot API error: $message");
  }

  return $result;
}

/**
 * Internal use only.
 */
function pardot_query($object, $params, $config) {
  return pardot_auth_request($object, 'query', null, null, $params, $config);
}

/**
 * Internal use only.
 */
function pardot_assign($object, $id_field, $id, $params, $config) {
  return pardot_auth_request($object, 'assign', $id_field, $id, $params, $config);
}

/**
 * Internal use only.
 */
function pardot_unassign($object, $id_field, $id, $params, $config) {
  return pardot_auth_request($object, 'unassign', $id_field, $id, $params, $config);
}

/**
 * Internal use only.
 */
function pardot_create($object, $id_field, $id, $params, $config) {
  return pardot_auth_request($object, 'create', $id_field, $id, $params, $config);
}

/**
 * Internal use only.
 */
function pardot_describe($object, $id_field, $id, $params, $config) {
  return pardot_auth_request($object, 'describe', $id_field, $id, $params, $config);
}

/**
 * Internal use only.
 */
function pardot_read($object, $id_field, $id, $params, $config) {
  return pardot_auth_request($object, 'read', $id_field, $id, $params, $config);
}

/**
 * Internal use only.
 */
function pardot_update($object, $id_field, $id, $params, $config) {
  return pardot_auth_request($object, 'update', $id_field, $id, $params, $config);
}

/**
 * Internal use only.
 */
function pardot_upsert($object, $id_field, $id, $params, $config) {
  return pardot_auth_request($object, 'upsert', $id_field, $id, $params, $config);
}

/**
 * Internal use only.
 */
function pardot_delete($object, $id_field, $id, $params, $config) {
  return pardot_auth_request($object, 'delete', $id_field, $id, $params, $config);
}

/**
 * Internal use only.
 */
function pardot_undelete($object, $id_field, $id, $params, $config) {
  return pardot_auth_request($object, 'undelete', $id_field, $id, $params, $config);
}

/**
 * Internal use only.
 */
function pardot_send($object, $id_field, $id, $params, $config) {
  return pardot_auth_request($object, 'send', $id_field, $id, $params, $config);
}

// Emails
/**
 * Returns the data for the specified email.
 * @param string $id      The ID of the email.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_read_email($id, $params = null, $config = null) {
  return pardot_read('email', 'id', $id, $params, $config);
}

/**
 * Sends an email to a prospect or a list.
 * @param string $id_field  Optional. If included, must be <code>prospect_id</code> or <code>prospect_email</code>. If,
 *                          omitted, email will be sent to a list.
 * @param string $id        Optional. If included, must be the ID or email address of the prospect according to the
 *                          value of the <code>$id_field</code> parameter.
 * @param array $params     See Pardot API documentation for details.
 * @param array $config     Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                          default.
 * @return array            The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_send_email($id_field = null, $id = null, $params = null, $config = null) {
  return pardot_send('email', $id_field, $id, $params, $config);
}

// Lists
/**
 * Returns the lists matching the specified criteria parameters.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_query_list($params = null, $config = null) {
  return pardot_query('list', $params, $config);
}

/**
 * Returns the data for the specified list.
 * @param string $id      The ID of the list.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_read_list($id, $params = null, $config = null) {
  return pardot_read('list', 'id', $id, $params, $config);
}

// Opportunities
/**
 * Returns the opportunities matching the specified criteria parameters.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_query_opportunity($params = null, $config = null) {
  return pardot_query('opportunity', $params, $config);
}

/**
 * Creates a new opportunity using the specified data.
 * @param string $id_field  The ID field to use. Must be <code>prospect_id</code> or <code>prospect_email</code>.
 * @param string $id        The ID or email address of the prospect according to the value of the <code>$id_field</code>
 *                          parameter.
 * @param array $params     An array of key/value pairs that must include, but is not limited to, <code>name</code>,
 *                          <code>value</code>, and <code>probability</code>. See Pardot API documentation for details.
 * @param array $config     Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                          default.
 * @return array            The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_create_opportunity($id_field, $id, $params, $config = null) {
  return pardot_create('opportunity', $id_field, $id, $params, $config);
}

/**
 * Returns the data for the specified opportunity, including campaign assignment and associated visitor activities.
 * @param string $id      The ID of the opportunity.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_read_opportunity($id, $params = null, $config = null) {
  return pardot_read('opportunity', 'id', $id, $params, $config);
}

/**
 * Updates the provided data for the specified opportunity.
 * @param string $id      The ID of the opportunity.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_update_opportunity($id, $params = null, $config = null) {
  return pardot_update('opportunity', 'id', $id, $params, $config);
}

/**
 * Deletes the specified opportunity.
 * @param string $id      The ID of the opportunity.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return boolean        <code>true</code> if successful.
 */
function pardot_delete_opportunity($id, $params = null, $config = null) {
  return pardot_delete('opportunity', 'id', $id, $params, $config);
}

/**
 * Undeletes the specified opportunity.
 * @param string $id      The ID of the opportunity.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return boolean        <code>true</code> if successful.
 */
function pardot_undelete_opportunity($id, $params = null, $config = null) {
  return pardot_undelete('opportunity', 'id', $id, $params, $config);
}

// Prospects
/**
 * Returns the prospects matching the specified criteria parameters.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_query_prospect($params = null, $config = null) {
  return pardot_query('prospect', $params, $config);
}

/**
 * Assigns or reassigns the specified prospect to a specified user or group.
 * @param string $id_field  The ID field to use. Must be <code>id</code> or <code>email</code>.
 * @param string $id        The ID or email address of the prospect according to the value of the <code>$id_field</code>
 *                          parameter.
 * @param array $params     An array of key/value pairs that must include, but is not limited to, only one of
 *                          <code>user_email</code>, <code>user_id</code>, or <code>group_id</code>. See Pardot API
 *                          documentation for details.
 * @param array $config     Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                          default.
 * @return array            The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_assign_prospect($id_field, $id, $params, $config = null) {
  return pardot_assign('prospect', $id_field, $id, $params, $config);
}

/**
 * Unassigns the specified prospect.
 * @param string $id_field  The ID field to use. Must be <code>id</code> or <code>email</code>.
 * @param string $id        The ID or email address of the prospect according to the value of the <code>$id_field</code>
 *                          parameter.
 * @param array $params     Optional. See Pardot API documentation for details.
 * @param array $config     Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                          default.
 * @return array            The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_unassign_prospect($id_field, $id, $params = null, $config = null) {
  return pardot_unassign('prospect', $id_field, $id, $params, $config);
}

/**
 * Creates a new prospect using the specified data.
 * @param string $email   The email address of the prospect.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_create_prospect($email, $params = null, $config = null) {
  return pardot_create('prospect', 'email', $email, $params, $config);
}

/**
 * Returns data for the specified prospect, including campaign assignment, profile criteria matching statuses,
 * associated visitor activities, email list subscriptions, and custom field data.
 * @param string $id_field  The ID field to use. Must be <code>id</code> or <code>email</code>.
 * @param string $id        The ID or email address of the prospect according to the value of the <code>$id_field</code>
 *                          parameter.
 * @param array $params     Optional. See Pardot API documentation for details.
 * @param array $config     Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                          default.
 * @return array            The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_read_prospect($id_field, $id, $params = null, $config = null) {
  return pardot_read('prospect', $id_field, $id, $params, $config);
}

/**
 * Updates the provided data for a specified prospect.
 * @param string $id_field  The ID field to use. Must be <code>id</code> or <code>email</code>.
 * @param string $id        The ID or email address of the prospect according to the value of the <code>$id_field</code>
 *                          parameter.
 * @param array $params     Optional. See Pardot API documentation for details.
 * @param array $config     Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                          default.
 * @return array            The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_update_prospect($id_field, $id, $params = null, $config = null) {
  return pardot_update('prospect', $id_field, $id, $params, $config);
}

/**
 * Updates the provided data for the specified prospect or creates a new one if it doesn't exist.
 * @param string $id_field  The ID field to use. Must be <code>id</code> or <code>email</code>.
 * @param string $id        The ID or email address of the prospect according to the value of the <code>$id_field</code>
 *                          parameter.
 * @param array $params     Optional. See Pardot API documentation for details.
 * @param array $config     Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                          default.
 * @return array            The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_upsert_prospect($id_field, $id, $params = null, $config = null) {
  return pardot_upsert('prospect', $id_field, $id, $params, $config);
}

/**
 * Deletes the specified prospect.
 * @param string $id_field  The ID field to use. Must be <code>id</code> or <code>email</code>.
 * @param string $id        The ID or email address of the prospect according to the value of the <code>$id_field</code>
 *                          parameter.
 * @param array $params     Optional. See Pardot API documentation for details.
 * @param array $config     Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                          default.
 * @return boolean          <code>true</code> if successful.
 */
function pardot_delete_prospect($id_field, $id, $params = null, $config = null) {
  return pardot_delete('prospect', $id_field, $id, $params, $config);
}

// Prospect Accounts
/**
 * Returns the prospect accounts matching the specified criteria parameters.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_query_prospect_account($params = null, $config = null) {
  return pardot_query('prospectAccount', $params, $config);
}

/**
 * Creates a new prospect account.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_create_prospect_account($params = null, $config = null) {
  return pardot_create('prospectAccount', $params, $config);
}

/**
 * Returns the field metadata for prospect accounts, explaining what fields are available, their types, whether they are
 * required, and their options (for dropdowns, radio buttons, etc).
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_describe_prospect_account($params = null, $config = null) {
  return pardot_describe('prospectAccount', $params, $config);
}

/**
 * Returns the data for the specified prospect account.
 * @param string $id      The ID of the prospect account.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_read_prospect_account($id, $params = null, $config = null) {
  return pardot_read('prospectAccount', 'id', $id, $params, $config);
}

/**
 * Update the data for the specified prospect account.
 * @param string $id      The ID of the prospect account.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_update_prospect_account($id, $params = null, $config = null) {
  return pardot_update('prospectAccount', 'id', $id, $params, $config);
}

// Users
/**
 * Returns the users matching the specified criteria parameters.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_query_user($params = null, $config = null) {
  return pardot_query('user', $params, $config);
}

/**
 * Returns the data for the specified user.
 * @param string $id_field  The ID field to use. Must be <code>id</code> or <code>email</code>.
 * @param string $id        The ID or email address of the user according to the value of the <code>$id_field</code>
 *                          parameter.
 * @param array $params     Optional. See Pardot API documentation for details.
 * @param array $config     Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                          default.
 * @return array            The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_read_user($id_field, $id, $params = null, $config = null) {
  return pardot_read('user', $id_field, $id, $params, $config);
}

// Visits
/**
 * Returns the visits matching the specified criteria parameters.
 * @param array $params   An array of key/value pairs that must include, but is not limited to, at least one of
 *                        <code>ids</code>, <code>visitor_ids</code>, or <code>prospect_ids</code>. See Pardot API
 *                        documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_query_visit($params, $config = null) {
  return pardot_query('visit', $params, $config);
}

/**
 * Returns the data for the specified visit.
 * @param string $id      The ID of the visit.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_read_visit($id, $params = null, $config = null) {
  return pardot_read('visit', 'id', $id, $params, $config);
}

// Visitors
/**
 * Returns the visitors matching the specified criteria parameters.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_query_visitor($params = null, $config = null) {
  return pardot_query('visitor', $params, $config);
}

/**
 * Assigns or reassigns the specified visitor to a specified prospect.
 * @param string $id      The ID of the visitor.
 * @param array $params   An array of key/value pairs that must include, but is not limited to, only one of
 *                        <code>prospect_id</code>, or <code>prospect_email</code>. See Pardot API documentation for
 *                        details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_assign_visitor($id, $params, $config = null) {
  return pardot_assign('visitor', 'id', $id, $params, $config);
}

/**
 * Returns the data for the specified visitor.
 * @param string $id      The ID of the visitor.
 * @param array $params   Optional. See Pardot API documentation for details.
 * @param array $config   Optional. The configuration for this API call. Uses global <code>$pardot_config</code> by
 *                        default.
 * @return array          The result of the Pardot API call. See Pardot API documentation for details.
 */
function pardot_read_visitor($id, $params = null, $config = null) {
  return pardot_read('visitor', 'id', $id, $params, $config);
}
