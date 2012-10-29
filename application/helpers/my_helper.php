<?php

function h($h)
{
    if ( is_array($h) )
        return array_map('h', $val);
    return htmlspecialchars($h);
}

function p($x)
{
    $ci =& get_instance();
    return $ci->input->post($x);
}

function g($x)
{
    $ci =& get_instance();
    return $ci->input->get($x);
}

function r($x)
{
    $ci =& get_instance();
    return $ci->input->get_post($x);
}

function is_post_request()
{
    return $_SERVER['REQUEST_METHOD'] == 'POST';
}

function flashmsg_set($msg)
{
    if (!isset($_SESSION))
        @session_start();
    $_SESSION['_flashmsg'] = $msg;
}

function flashmsg_get()
{
    if (!isset($_SESSION))
        @session_start();
    $msg = isset($_SESSION['_flashmsg']) ? $_SESSION['_flashmsg'] : null;
    if (!is_null($msg)) unset($_SESSION['_flashmsg']);
    return $msg;
}

function auto_code($prefix)
{
    $ci  = & get_instance();
    $ci->db->query("INSERT INTO auto_code (prefix, sequence) VALUES ( ?, 1 ) ON DUPLICATE KEY UPDATE sequence  =  sequence + 1", array($prefix));
    $result  =  $ci->db->query("SELECT sequence FROM auto_code WHERE prefix  =  ?", array($prefix));
    $row  =  $result->row();
    $result  =  strtoupper($prefix) . '-' . str_pad($row->sequence, 5, '0', STR_PAD_LEFT);
    return $result;
}

function unique_id($stub)
{
    require_once APPPATH.'libraries/verhoeff.php';

    $ci  = & get_instance();
    $ci->db->query("REPLACE INTO Tickets32 (stub) VALUES (?)", array($stub));
    $result  =  $ci->db->insert_id();
    $result = verhoeff::generate($result);
    return $result;
}

function secure_seed_rng($count=8)
{
    $output = '';

    // Try the OpenSSL method first. This is the strongest.
    if(function_exists('openssl_random_pseudo_bytes'))
    {
        $output = openssl_random_pseudo_bytes($count, $strong);
        if($strong !== true)
        {
            $output = '';
        }
    }

    if($output == '')
    {
        // Then try the unix/linux method
        if(@is_readable('/dev/puxurandom') && ($handle = @fopen('/dev/urandom', 'rb')))
        {
            $output = @fread($handle, $count);
            @fclose($handle);
        }
		else if(version_compare(PHP_VERSION, '5.0.0', '>=') && class_exists('COM'))
        {
			// Then try the Microsoft method
            try {
                $util = new COM('CAPICOM.Utilities.1');
                $output = base64_decode($util->GetRandom($count, 0));
            }
            catch(Exception $ex) { }
        }
    }

    // Didn't work? Do we still not have enough bytes? Use our own (less secure) rng generator
    if(strlen($output) < $count)
    {
        $output = '';

        // Close to what PHP basically uses internally to seed, but not quite.
        $unique_state = microtime().getmypid();

        for($i = 0; $i < $count; $i += 16)
        {
            $unique_state = md5(microtime().$unique_state);
            $output .= pack('H*', md5($unique_state));
        }
    }

    // /dev/urandom and openssl will always be twice as long as $count. base64_encode will roughly take up 33% more space but crc32 will put it to 32 characters
    $output = hexdec(substr(dechex(crc32(base64_encode($output))), 0, $count));

    return $output;
}

function my_rand($min=null, $max=null, $force_seed=false)
{
    static $seeded = false;
    static $obfuscator = 0;

    if($seeded == false || $force_seed == true)
    {
        mt_srand(secure_seed_rng());
        $seeded = true;

        $obfuscator = abs((int) secure_seed_rng());

        // Ensure that $obfuscator is <= mt_getrandmax() for 64 bit systems.
        if($obfuscator > mt_getrandmax())
        {
            $obfuscator -= mt_getrandmax();
        }
    }

    if($min !== null && $max !== null)
    {
        $distance = $max - $min;
        if ($distance > 0)
        {
            return $min + (int)((float)($distance + 1) * (float)(mt_rand() ^ $obfuscator) / (mt_getrandmax() + 1));
        }
        else
        {
            return mt_rand($min, $max);
        }
    }
    else
    {
        $val = mt_rand() ^ $obfuscator;
        return $val;
    }
}


function fix_float( $array )
{
    if ( is_array( $array ) )
    {
        foreach( $array as $k => $v )
        {
            $array[$k] = fix_float($v);
        }
        return $array;
    }
    else
    {
        return is_float($array) ? round($array,2) : $array;
    }
}

function parse_filter( $filter, $columns=array(), $table='' )
{
    $ci =& get_instance();
    
    if ($table) $table .= ".";
    if ($filter)
    {
        $filter = json_decode($filter);
        if ( $filter && is_array($filter) )
        {
            $first = false;
            
            foreach( $filter as $f )
            {
                if (is_null($f->value)) continue;
                
                if ( isset($f->property) )
                {
                    //search mode
                    if ( !in_array($f->property, $columns ) )
                        continue;
                    
                    //$words = array_unique(array_filter(array_map('trim', explode(' ',$f->value))));
                    //foreach( $words as $word )
                    //{
                        if ( !$first )
                            $ci->db->like( $table.$f->property, $f->value );
                        else
                            $ci->db->or_like( $table.$f->property, $f->value );
                    //}
                }
                else if ( isset($f->field) )
                {
                    //filter mode
                    if ( !in_array($f->field, $columns ) )
                        continue;
                    
                    if ( isset($f->type) && $f->type == 'numeric' ) {
                        switch( $f->comparison ) {
                            case 'lt':
                                $ci->db->where( $table.$f->field.' < ', $f->value );
                                break;
                            case 'gt':
                                $ci->db->where( $table.$f->field.' > ', $f->value );
                                break;
                            default:
                                $ci->db->where( $table.$f->field, $f->value );
                        }
                    } else if ( isset($f->type) && $f->type == 'boolean' ) {
                        $ci->db->where( $table.$f->field, $f->value );
                    } else {
                        if ( !$first )
                            $ci->db->like( $table.$f->field, $f->value);
                        else
                            $ci->db->or_like( $table.$f->field, $f->value);
                    }
                }
            }
        }
    }
    
    return $ci->db;
}

function parse_filter2( $filter, $columns=array(), $table='' )
{
    $ci =& get_instance();
    
    if ($table) $table .= ".";

    $where = "";
    
    if ($filter)
    {
        $filter = json_decode($filter);
        if ( $filter && is_array($filter) )
        {
            $first = false;
            
            foreach( $filter as $f )
            {
                if (is_null($f->value)) continue;
                
                if ( isset($f->property) )
                {
                    //search mode
                    if ( !in_array($f->property, $columns ) )
                        continue;
                    
                    //$words = array_unique(array_filter(array_map('trim', explode(' ',$f->value))));
                    //foreach( $words as $word )
                    //{
                        if ( !$first )
                            $where .= " AND $table".$f->property." LIKE ".$ci->db->escape("%".$f->value."%");
                        else
                            $where .= " OR $table.".$f->property." LIKE ".$ci->db->escape("%".$f->value."%");
                    //}
                }
                else if ( isset($f->field) )
                {
                    //filter mode
                    if ( !in_array($f->field, $columns ) )
                        continue;
                    
                    if ( isset($f->type) && $f->type == 'numeric' ) {
                        switch( $f->comparison ) {
                            case 'lt':
                                $where .= " AND $table".$f->field." < ".$ci->db->escape($f->value);
                                break;
                            case 'gt':
                                $where .= " AND $table".$f->field." > ".$ci->db->escape($f->value);
                                break;
                            default:
                                $where .= " AND $table".$f->field." = ".$ci->db->escape($f->value);
                        }
                    } else if ( isset($f->type) && $f->type == 'boolean' ) {
                        $where .= " AND $table".$f->field." = ".$ci->db->escape($f->value);
                    } else {
                        if ( !$first )
                            $where .= " AND $table".$f->property." LIKE ".$ci->db->escape("%".$f->value."%");
                        else
                            $where .= " OR $table.".$f->property." LIKE ".$ci->db->escape("%".$f->value."%");
                    }
                }
            }
        }
    }
    
    return $where;
}

function parse_sort($sort, $columns, $table='')
{
    $ci =& get_instance();
    if ($table) $table .= ".";

    if ( $sort )
    {
        $sort = json_decode($sort);
        if ( $sort && is_array($sort) )
        {
            foreach($sort as $s)
            {
                if ( !in_array($s->property, $columns) )
                    continue;
                $ci->db->order_by( $table.$s->property, $s->direction );
            }
        }
    }
    
    return $ci->db;
}

function parse_sort2($sort, $columns, $table='')
{
    $ci =& get_instance();
    if ($table) $table .= ".";
    
    $order = '';
    
    if ( $sort )
    {
        $sort = json_decode($sort);
        if ( $sort && is_array($sort) )
        {
            foreach($sort as $s)
            {
                if ( !in_array($s->property, $columns) )
                    continue;
                $order .= ($order ? "," : "") . $table.$s->property . " " . $s->direction;
            }
        }
    }
    
    return $order ? "ORDER BY $order" : "";
}

function html2plain($html)
{
    $html = preg_replace('~/\s*>~', '>', $html);
    $html = preg_replace('~<br[^/>]+/?>~i', "\n", $html);
    $html = preg_replace('~<p[^>]+>~i', "\n\n", $html);
    $html = preg_replace('~<h[1-6][^>]+>~i', "\n", $html);
    $html = preg_replace('~<div[^>]+>~i', "\n", $html);
    $html = preg_replace('~<t[hd][^>]+>~i', " ", $html);
    $html = preg_replace('~<tr[^>]+>~i', "\n\n", $html);
    //$html = preg_replace('~<a[^h]+href\s*=\s*\"([^"]+)\">[^<]+</a>~i', "\1", $html);
    $html = strip_tags($html);
    $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
    return trim($html);
}

if (!class_exists('template_parser'))
{
    class template_parser
    {
        var $context = array();
        
        var $maps = array(
            'customer_name', 
            'customer_email', 
            'customer_phone', 
            'customer_mobile', 
            'campaign_title',
            'campaign_description',
            'sent_date',
            'company_name',
            'company_email',
            'company_website',
            'company_phone',
        );
        
        var $map_keys = array();
        
        function callback($matches)
        {
            if (!$this->map_keys)
                $this->map_keys = array_flip($this->maps);
            
            $key = trim($matches[1]);
            $key = strtolower($key);
            
            if ( isset($this->map_keys[$key]) )
            {
                return isset($this->context[$key]) ? $this->context[$key] : '';
            }
            else
            {
                return '';
            }
        }
        
        function parse($html, $context)
        {
            $this->context = $context;
            return preg_replace_callback('~{%([^%]+)*%}~i', array($this, 'callback'), $html);
        }
        
        function make_context( $customer, $campaign, $client )
        {
            $context = array(
                'customer_name' => $customer->first_name .  ' ' . $customer->last_name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'customer_mobile' => $customer->mobile,
                'customer_website' => $customer->website,
                
                'company_name' => $client->name,
                'company_email' => $client->email,
                'company_phone' => $client->phone,
                'company_website' => $client->website,
                
                'campaign_title' => $campaign->campaign_title,
                'campaign_description' => $campaign->campaign_description,
                
                'sent_date' => date('d/M/Y'),
            );
            return $context;
        }
    }
}

if (!class_exists('invoice_template'))
{
    class invoice_template
    {
        var $context = array();
        var $maps = array(
            'customer_name', 
            'customer_email', 
            'customer_address', 
            'company_name',
            'company_email',
            'company_website',
            'company_phone',
            'company_address',
            'invoice_id',
            'invoice_date',
            'invoice_due',
            'invoice_total',
            'invoice_discount',
            'invoice_tax',
            'invoice_grand_total',
            'item_no',
            'item_desc',
            'item_price',
            'item_code',
            'item_qty',
            'item_tax',
            'item_disccount',
            'item_subtotal',
            'item_total',
        );
        
        var $map_keys = array();
        
        function callback($matches)
        {
            if (!$this->map_keys)
                $this->map_keys = array_flip($this->maps);
            
            $key = trim($matches[1]);
            $key = strtolower($key);
            
            if ( isset($this->map_keys[$key]) )
            {
                return isset($this->context[$key]) ? $this->context[$key] : '';
            }
            else
            {
                return '';
            }
        }
        
        function parse($html, $invoice, $client, $details)
        {
            $this->context = $this->make_context($invoice, $client, null);
            
            //ambil detail header
            if (preg_match('~(.*?){%DETAIL_START%}(.*?){%DETAIL_END%}(.*)~is', $html, $match))
            {
                $header = $match[1];
                $detail = $match[2];
                $footer = $match[3];
                
                $header = preg_replace_callback('~{%([^%]+)*%}~i', array($this, 'callback'), $header);
                $footer = preg_replace_callback('~{%([^%]+)*%}~i', array($this, 'callback'), $footer);
                $detail_html = '';
                
                foreach($details as $k => $item)
                {
                    $this->context = $this->make_context($invoice, $client, $item);
                    $this->context['item_no'] = $k+1;
                    $detail_html .= preg_replace_callback('~{%([^%]+)*%}~i', array($this, 'callback'), $detail);
                }
                
                return $header . $detail_html . $footer;
            }
            else
            {
                return preg_replace_callback('~{%([^%]+)*%}~i', array($this, 'callback'), $html);
            }
        }
        
        function make_context( $invoice, $client, $detail )
        {
            $context = array(
                'customer_name' => $invoice->to_name,
                'customer_email' => $invoice->to_email,
                'customer_address' => $invoice->to_address,
                
                'company_name' => $client->name,
                'company_email' => $client->email,
                'company_phone' => $client->phone,
                'company_website' => $client->website,
                'company_address' => "{$client->address}, $client->city, $client->state, $client->country, $client->zip_code",
                
                'invoice_id' => $invoice->invoice_id,
                'invoice_date' => date('d/m/Y', strtotime($invoice->create_date)),
                'invoice_due' => date('d/m/Y', strtotime($invoice->due_date)),
                        
                'invoice_total' => number_format($invoice->subtotal, 1),
                'invoice_tax' => number_format($invoice->tax,1),
                'invoice_discount' => number_format($invoice->discount,1),
                'invoice_grand_total' => number_format($invoice->total,1),
                        
                'invoice_pay_amount' => number_format($invoice->pay_total,1),
                'invoice_pay_date' => $invoice->pay_date ? date('d/m/Y', strtotime($invoice->pay_date)) : 'outstanding',
            );
            
            if ($detail)
            {
                //{%ITEM_CODE%}{%ITEM_DESC%}{%ITEM_PRICE%}{%ITEM_QTY%}{%ITEM_SUBTOTAL%}{%ITEM_DISCOUNT%}{%ITEM_TAX%}{%ITEM_TOTAL%}
                $context['item_code'] = $detail->product_code;
                $context['item_desc'] = $detail->description;
                $context['item_price'] = $detail->price;
                $context['item_qty'] = number_format($detail->quantity,1);
                $context['item_subtotal'] = number_format($detail->subtotal,1);
                $context['item_tax'] = number_format($detail->tax,1);
                $context['item_discount'] = number_format($detail->discount,1);
                $context['item_total'] = number_format($detail->total,1);
            }
                
            return $context;
        }
        
        function plain_template()
        {
            return '                
Invoice #{%INVOICE_ID%}
Date: {%INVOICE_DATE%}
Due: {%INVOICE_DUE%}
Company: {%COMPANY_NAME%} 
{%COMPANY_ADDRESS%}

Customers:
{%CUSTOMER_NAME%}
{%CUSTOMER_ADDRESS%}

Details:
{%DETAIL_START%}
Item #{%ITEM_NO%}
Name: {%ITEM_DESC%} [{%ITEM_CODE%}]
Price: {%ITEM_PRICE%}
Quantity: {%ITEM_QTY%}
Total: {%ITEM_TOTAL%}
{%DETAIL_END%}

SubTotal: {%INVOICE_TOTAL%}
Discount: {%INVOICE_DISCOUNT%}
Tax: {%INVOICE_TAX%}
Total: {%INVOICE_TOTAL%}

--
Thank you
';
        }
        
        function default_template()
        {
            return '<center>
<div style="background:#fff;border:none;font-family:arial,sans-serif;width:600px;">
<table id="pageHeader" style="width:600px;margin:0;padding:0;">
<tr>
	<td style="text-align:left;width:50%;">{%COMPANY_NAME%}</td>
	<td style="text-align:right;width:50%;"><div style="font-size:11px;color:#666;line-height:1;">{%COMPANY_ADDRESS%}</div></td>
</tr>
</table>
<table id="invoiceHeader" style="width:600px;margin:10px 0;padding:0;">
<tr>
	<td style="text-align:left;width:50%;">
		<h1 style="font-size:20px;line-height:1;">
                    Invoice #{%INVOICE_ID%}<br />
                    Date: {%INVOICE_DATE%}
                </h1>
	</td>
	<td style="text-align:right;width:50%;">
		<div style="font-size:14px;line-height:1;">
		<p><b>To: {%CUSTOMER_NAME%}</b></p>
                <p>{%CUSTOMER_ADDRESS%}</p>
		</div>
	</td>
</tr>
</table>
<table id="invoiceTable" style="background-color:#FFF;width:600px;margin:0;padding:0;font-size:11px;">
<thead>
	<tr>
		<th style="width:20px; background-color:#BFDFF4;font-size:14px;padding:5px;color:#fff;">#</th>
		<th style="width:50%;background-color:#BFDFF4;font-size:14px;padding:5px;color:#fff;">Description</th>
		<th style="text-align:right;background-color:#BFDFF4;font-size:14px;padding:5px;color:#fff;">Price</th>
		<th style="text-align:right;background-color:#BFDFF4;font-size:14px;padding:5px;color:#fff;">Quantity</th>
		<th style="text-align:right;background-color:#BFDFF4;font-size:14px;padding:5px;color:#fff;">Total</th>
	</tr>
</thead>
</table>

{%DETAIL_START%}
<table style="background-color:#FFF;width:600px;margin:0;padding:0;font-size:11px;">
<tbody>
	<tr>
		<td style="width:20px;padding:5px;color:#555;">{%ITEM_NO%}</td>
		<td style="width:50%;padding:5px;color:#555;">{%ITEM_DESC%} [{%ITEM_CODE%}]</td>
		<td style="text-align:right;padding:5px;color:#555;">{%ITEM_PRICE%}</td>
		<td style="text-align:right;padding:5px;color:#555;">{%ITEM_QTY%}</td>
		<td style="text-align:right;padding:5px;color:#555;">{%ITEM_TOTAL%}</td>
	</tr>
</tbody>
</table>
{%DETAIL_END%}

<table style="background-color:#FFF;width:600px;margin:0;padding:0;font-size:11px;">
<tfoot>
	<tr>
		<th style="width:80%; text-align:right;font-size:14px;font-weight:bold;padding:5px;">SUBTOTAL</th>
		<th style="text-align:right;font-size:14px;font-weight:bold;color:#fff;background:#000;padding:5px;">{%INVOICE_TOTAL%}</th>
	</tr>
	<tr>
		<th style="width:80%; text-align:right;font-size:14px;font-weight:bold;padding:5px;">DISKON</th>
		<th style="text-align:right;font-size:14px;font-weight:bold;color:#fff;background:#000;padding:5px;">{%INVOICE_DISCOUNT%}</th>
	</tr>
	<tr>
		<th style="width:80%; text-align:right;font-size:14px;font-weight:bold;padding:5px;">PAJAK</th>
		<th style="text-align:right;font-size:14px;font-weight:bold;color:#fff;background:#000;padding:5px;">{%INVOICE_TAX%}</th>
	</tr>
	<tr>
		<th style="width:80%; text-align:right;font-size:14px;font-weight:bold;padding:5px;">TOTAL</th>
		<th style="text-align:right;font-size:14px;font-weight:bold;color:#fff;background:#000;padding:5px;">{%INVOICE_GRAND_TOTAL%}</th>
	</tr>
</tfoot>
</table>

<table id="invoiceFooter" style="width:600px;margin:10px 0 0 0;padding:0;">
<tr>
	<td style="text-align:left;width:50%;font-size:11px;background:#00AEEF;color:#fff;">
		<p>Thank you<br />Please remit payment before <b>{%INVOICE_DUE%}</b></p>
	</td>
	<td style="text-align:right;width:50%;">
		<div style="font-size:11px;line-height:1;">
		{%COMPANY_NAME%}<br />
		{%COMPANY_ADDRESS%}<br />
		{%COMPANY_EMAIL%} {%COMPANY_PHONE%}<br />
		</div>
	</td>
</tr>
</table>
</div>
</center>
';
        }
    }
}

function time_since2($older_date, $newer_date = false, $format='')
{
    if ( !is_numeric($older_date) )
        $older_date = strtotime($older_date);

    if ( !is_numeric($newer_date) && $newer_date )
        $newer_date = strtotime($newer_date);

    // $newer_date will equal false if we want to know the time elapsed between a date and the current time
    // $newer_date will have a value if we want to work out time elapsed between two known dates
    $newer_date = ($newer_date == false) ? time() : $newer_date;

    // difference in seconds
    $since = $newer_date - $older_date;

    if ( $since > 86400 )
    {
        return date( 'l, d/M/Y H:i', $older_date );
    }

    // array of time period chunks
    global $time_chunks;
    if (!isset($time_chunks))
    {
        $chunks = array(
            array(60 * 60 * 24 * 365 , 'tahun'),
            array(60 * 60 * 24 * 30 , 'bulan'),
            array(60 * 60 * 24 * 7, 'minggu'),
            array(60 * 60 * 24 , 'hari'),
            array(60 * 60 , 'jam'),
            array(60 , 'menit'),
        );
        $time_chunks = $chunks;
    }
    else
    {
        $chunks = $time_chunks;
    }

    // we only want to output two chunks of time here, eg:
    // x years, xx months
    // x days, xx hours
    // so there's only two bits of calculation below:

    // step one: the first chunk
    for ($i = 0, $j = count($chunks); $i < $j; $i++)
    {
        $seconds = $chunks[$i][0];
        $name = $chunks[$i][1];

        // finding the biggest chunk (if the chunk fits, break)
        if (($count = floor($since / $seconds)) != 0)
        {
            break;
        }
    }

    // set output var
    $output = "$count {$name}";

    // step two: the second chunk
    if ($i + 1 < $j)
    {
        $seconds2 = $chunks[$i + 1][0];
        $name2 = $chunks[$i + 1][1];

        if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0)
        {
            // add to output var
            $output .= ", $count2 {$name2}";
        }
    }

    return "$output";
}

