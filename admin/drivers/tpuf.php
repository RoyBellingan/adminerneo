<?php

namespace AdminNeo;

add_driver("tpuf", "TPUF (alpha)");

function trace() {
    $trace = array_map(function($call) {
        $file = basename($call['file']); // Get just the filename without path
        return sprintf("%s:%d %s()", $file, $call['line'], $call['function']);
    }, array_slice(debug_backtrace(), 0, -1));
    return $trace;
}

if (isset($_GET["tpuf"])) {
	define("AdminNeo\DRIVER", "tpuf");

	class Min_DB {
		var $extension = "JSON", $server_info, $errno, $_result, $error, $_url;
		var $_db = 'devroy__inputs_1_v2';
        var $table;
        var $url;
        var $key;
        var $passHash = null;

        var $httpHeaders = [];

        function handleHeaderLine( $curl, $header_line, ) {
            $this->httpHeaders[] = $header_line;
            return strlen($header_line);
        }

        function curl($url, $content, $headers, $get = false) {

            $headers['Authorization'] = 'Bearer ' . $this->key;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            
            if ($get) {
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            } else {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
            }
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, [$this, 'handleHeaderLine']);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $info = curl_getinfo($ch);

            if ($http_code !== 200) {
                die("HTTP error for $url: " . $http_code . " " . $response . "\n  for query $content <xmp>" . print_r(trace(), true) . "</xmp>");
            }

            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                die("CURL error for $url: " . $error . " " . $response . "\n  for query $content <xmp>" . print_r(trace(), true) . "</xmp>");
            }

            return $response;
        }

		/**
		 * @param string $query
		 * @return bool|Min_Result
		 */
		function query($query) {
            $headers = [
                'Content-Type' => 'application/json'
            ];
            $url = $this->url . "/v1/namespaces/" . $this->table . "/query";

			$response = $this->curl($url, json_encode($query), $headers, false);

			$return = json_decode($response, true);
			
			// Process the response to move attributes to the same level as id
			if (is_array($return)) {
				foreach ($return as $key => $item) {
					if (isset($item['attributes']) && is_array($item['attributes'])) {
						foreach ($item['attributes'] as $attrKey => $attrValue) {
							$return[$key][$attrKey] = $attrValue;
						}
						unset($return[$key]['attributes']);
                        ksort($return[$key]);
                        
						// If dist column is present, move it to the first position
						if (isset($return[$key]['dist'])) {
							$dist = $return[$key]['dist'];
							unset($return[$key]['dist']);
							$return[$key] = ['dist' => $dist] + $return[$key];
						}
					}
				}
			}

			return new Min_Result($return);
		}

		/**
		 * @param string $server
		 * @param string $username
		 * @param string $password
		 * @return bool
		 */
		function connect($server, $username, $password) {
            $this->url = $server;
            $this->key = $password;
            $this->passHash = hash('sha256', $password);
			// $query = [
            //     "top_k" => 1,
            //     "include_attributes" => ["id"]
            // ];

			// $return = $this->query($query);

			return true;
		}

		function select_db($database) {
			$this->_db = $database;
			return true;
		}

		function quote($string) {
			return "'" . addcslashes($string, "\\'") . "'";
		}

		function multi_query($query) {
			return $this->_result = $this->query($query);
		}

		function store_result() {
			return $this->_result;
		}

		function next_result() {
			return false;
		}

		function result($query, $field = 0) {
			$result = $this->query($query);
			return $result['data'];
		}
	}

	class Min_Result {
		var $num_rows, $_rows, $columns, $_offset = 0;

		/**
		 * @param array $rows
		 */
		function __construct($rows) {
			$this->_rows = $rows;
			$this->num_rows = sizeof($rows);
			
			if (!empty($rows)) {
				$firstRow = reset($rows);
				$this->columns = array_keys($firstRow);
			} else {
				$this->columns = [];
			}
			
			reset($this->_rows);
		}

		function fetch_assoc() {
			$row = current($this->_rows);
			next($this->_rows);
			return $row === false ? false : array_combine($this->columns, $row);
		}

		function fetch_row() {
			$row = current($this->_rows);
			next($this->_rows);
			return $row;
		}

		function fetch_field() {
			$column = $this->_offset++;
			$return = new \stdClass;
			if ($column < count($this->columns)) {
				$return->name = $this->meta[$column]['name'];
				$return->orgname = $return->name;
				$return->type = $this->meta[$column]['type'];
			}
			return $return;
		}
	}


	class Min_Driver extends Min_SQL {
		function delete($table, $queryWhere, $limit = 0) {
            throw new \Exception("DELETE is not supported");
		}

		function update($table, $set, $queryWhere, $limit = 0, $separator = "\n") {
			throw new \Exception("UPDATE is not supported");
		}

		function insert($table, $set) {
			throw new \Exception("INSERT is not supported");
		}

		function insertUpdate($table, $rows, $primary) {
			throw new \Exception("INSERT UPDATE is not supported");
		}

		function begin() {
			throw new \Exception("BEGIN is not supported");
		}

		function commit() {
			throw new \Exception("COMMIT is not supported");
		}

		function rollback() {
			throw new \Exception("ROLLBACK is not supported");
		}

        /** Select data from table
        * @param string
        * @param array result of Admin::get()->processSelectionColumns()[0]
        * @param array result of Admin::get()->processSelectionSearch()
        * @param array result of Admin::get()->processSelectionColumns()[1]
        * @param array result of Admin::get()->processSelectionOrder()
        * @param ?int result of Admin::get()->processSelectionLimit()
        * @param int index of page starting at zero
        * @param bool whether to print the query
        * @return Min_Result
        */
        function select($table, $select, $where, $group, $order = [], ?int $limit = 1, $page = 0, $print = false) {
            $query = [
                'top_k' => $limit
            ];

            if ($select[0] == "*") {
                $query["include_attributes"] = true;// do not pass vector or will fail ! remove it array_keys(fields($table));
            }
            if ($select[0] == "COUNT(*)") {
                $query["count"] = true;
            }

            $filters = ["And", []];
            foreach ((array) $_GET["where"] as $where) {
                $col = $where["col"];
                $op = $where["op"];
                $val = $where["val"];
                if ($col == "") {
                    continue;
                }
                if($col == "vector") {
                    if($op == "Eq"){
                        $query["vector"] = json_decode($val, true);
                    }else if($op == "EmbedV1"){
                        $query["vector"] = embed($val,1);
                    }else if($op == "EmbedV2"){
                        $query["vector"] = embed($val,2);
                    }else{
                        die("unsupported operator for vector: $op");
                    }
                    $query["distance_metric"] = "cosine_distance";
                    continue;
                }

                $filters[1][] = [$col, $op, $val];
            }
            if (!empty($filters[1])) {
                $query["filters"] = $filters;
            }
            
            $start = microtime(true);
            $this->_conn->table = $table;
            $return = $this->_conn->query($query);
            if ($print) {
                echo Admin::get()->formatSelectQuery(json_encode($query), $start, !$return);
            }
            
            return $return;
        }

    }
	function idf_escape($idf) {
		return "`" . str_replace("`", "``", $idf) . "`";
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function explain($connection, $query) {
		return '';
	}

	function found_rows($table_status, $where) {
		//tpuf does not support this currently, it should, but is broken and ns always have 0
		return 0;
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		die("ALTER TABLE is not supported");
	}

	function truncate_tables($tables) {
		die("ALTER TABLE is not supported");
	}

	function drop_views($views) {
		die("ALTER TABLE is not supported");
	}

	function drop_tables($tables) {
		die("ALTER TABLE is not supported");
	}

	/**
	 * @param string $hostPath
	 * @return bool
	 */
	function is_server_host_valid($hostPath)
	{
		return strpos(rtrim($hostPath, '/'), '/') === false;
	}

	/**
	 * @return Min_DB|string
	 */
	function connect()
	{
		$connection = new Min_DB();

		$credentials = Admin::get()->getCredentials();
		if (!$connection->connect($credentials[0], $credentials[1], $credentials[2])) {
			return $connection->error;
		}

		return $connection;
	}

	function get_databases($flush) {
        //we do not have 
        return ["all"];
	}

	function limit($query, $where, ?int $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" . ($offset ? ", $offset" : "") : "");
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return limit($query, $where, 1, 0, $separator);
	}

	function db_collation($db, $collations) {
	}

	function engines() {
		return ['MergeTree'];
	}

	function logged_user() {
		$credentials = Admin::get()->getCredentials();

		return $credentials[1];
	}

	function tables_list() {
		global $connection;
		
		$cache_key = 'tpuf_namespaces_' . $connection->url . '_' . $connection->passHash;
		$cached_result = apcu_fetch($cache_key, $success);
		
		if ($success && $cached_result) {
			return $cached_result;
		}
		
		$headers = ['Content-Type' => 'application/json'];
		$return = [];
		$cursor = null;
		$page_size = 1000;
		
		do {
			$url = $connection->url . "/v1/namespaces?page_size=" . $page_size;
			if ($cursor) {
				$url .= "&cursor=" . urlencode($cursor);
			}
			
			$response = $connection->curl($url, json_encode([]), $headers, true);
			$response = json_decode($response, true);
			
			foreach ($response['namespaces'] as $namespace) {
				if (isset($namespace['id'])) {
					$return[$namespace['id']] = 'table';
				}
			}
			
			$cursor = isset($response['next_cursor']) ? $response['next_cursor'] : null;
		} while ($cursor);
		
		// Cache for 5 minutes
		apcu_store($cache_key, $return, 300);
		
		ksort($return);
		return $return;
	}

	function count_tables($databases) {
		return [];
	}

	function table_status($name = "", $fast = false) {
		$return = [];
		foreach (tables_list() as $table => $type) {
			$return[$table] = ["Name" => $table];
			if ($name == $table) {
				return $return[$table];
			}
		}
		return $return;
	}

	function is_view($table_status) {
		return false;
	}

	function fk_support($table_status) {
		return false;
	}

	function convert_field($field) {
	}

	function unconvert_field(array $field, $return) {
		if (in_array($field['type'], ["Int8", "Int16", "Int32", "Int64", "UInt8", "UInt16", "UInt32", "UInt64", "Float32", "Float64"])) {
			return "to$field[type]($return)";
		}
		return $return;
	}

	function fields($table) {
		global $connection;
		$return = [];
		
		$headers = ['Content-Type' => 'application/json'];
		$url = $connection->url . "/v1/namespaces/" . $table . "/schema";
		
		try {
			$cache_key = 'tpuf_schema_' . $table . '_' . $connection->url . '_' . $connection->passHash;
			$cached_result = apcu_fetch($cache_key, $success);
			
			if ($success && $cached_result) {
				return $cached_result;
			}
			
			$response = $connection->curl($url, "", $headers, true);
			$schema = json_decode($response, true);
			
			foreach ($schema as $field_name => $field_info) {
				$type = $field_info['type'];
                $full_text = $field_info['full_text_search'] ? "yes" : "no";
                $filterable = $field_info['filterable'] ? "yes" : "no";
                $type = $type . " filter: " . $filterable . " | full text: " . $full_text;  
				$nullable = false; // TurboPuffer schema doesn't appear to indicate nullability directly
				
				$return[$field_name] = [
					"field" => $field_name,
					"full_type" => $type,
					"type" => $type,
					"default" => "",
					"null" => $nullable,
					"auto_increment" => '0',
					"privileges" => ["insert" => 1, "select" => 1, "update" => 0, "where" => 1, "order" => 1],
				];
			}
			
			// Cache for 600 seconds
			apcu_store($cache_key, $return, 600);
			
		} catch (\Exception $e) {
			// Handle API error
		}

		return $return;
	}

	function indexes($table, $connection2 = null) {
		return [];
	}

	function foreign_keys($table) {
		return [];
	}

	function collations() {
		return [];
	}

	function information_schema($db) {
		return false;
	}

	function error() {
		global $connection;
		return h($connection->error);
	}

	function types() {
		return [];
	}

	function auto_increment() {
		return '';
	}

	function last_id() {
		return 0; // ClickHouse doesn't have it
	}

	function support($feature) {
		return preg_match("~^(columns|sql|status|table|drop_col)$~", $feature);
	}

	function driver_config() {
		$types = [];
		$structured_types = [];
		foreach ([ //! arrays
			lang('Numbers') => ["Int8" => 3, "Int16" => 5, "Int32" => 10, "Int64" => 19, "UInt8" => 3, "UInt16" => 5, "UInt32" => 10, "UInt64" => 20, "Float32" => 7, "Float64" => 16, 'Decimal' => 38, 'Decimal32' => 9, 'Decimal64' => 18, 'Decimal128' => 38],
			lang('Date and time') => ["Date" => 13, "DateTime" => 20],
			lang('Strings') => ["String" => 0],
			lang('Binary') => ["FixedString" => 0],
		] as $key => $val) {
			$types += $val;
			$structured_types[$key] = array_keys($val);
		}
		return [
			'jush' => "turbopuffer",
			'types' => $types,
			'structured_types' => $structured_types,
			'unsigned' => [],
			'operators' => ["Eq", "NotEq", "In", "NotIn", "Lt", "Lte", "Gt", "Gte", "Glob", "NotGlob", "IGlob", "NotIGlob","EmbedV1","EmbedV2", "ContainsAllTokens"],
			'functions' => [],
			'grouping' => [],
			'edit_functions' => [],
			"system_databases" => ["INFORMATION_SCHEMA", "information_schema", "system"],
		];
	}
}

function embed_v1($val) {
    include __DIR__ . '/token.php';
    
    $headers = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $OPENAI_API_KEY
    ];
    
    $url = "https://api.openai.com/v1/embeddings";
    $data = [
        'model' => 'text-embedding-3-large',
        'dimensions' => 1536,
        'input' => [$val]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code !== 200) {
        throw new \Exception("HTTP error: " . $http_code . " " . $response);
    }
    
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new \Exception("CURL error: " . $error);
    }
    
    $result = json_decode($response, true)['data'][0]['embedding'];

    return $result;
}

function embed_v2($val) {
    include __DIR__ . '/token.php';
    
    $headers = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $CLOUDFLARE_API_KEY
    ];
    
    $url = "https://api.cloudflare.com/client/v4/accounts/$CLOUDFLARE_ACCOUNT_ID/ai/run/@cf/baai/bge-m3";
    $data = [
        'text' => [$val]
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code !== 200) {
        throw new \Exception("HTTP error: " . $http_code . " " . $response);
    }
    
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new \Exception("CURL error: " . $error);
    }
    
    $result = json_decode($response, true)['result']["data"][0];

    return $result;
}

function embed($val, $version = 1) {
    switch($version) {
        case 1:
            return embed_v1($val);
        case 2:
            return embed_v2($val);
        default:
            die("unsupported version: $version");
    }
}