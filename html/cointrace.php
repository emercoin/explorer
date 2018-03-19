<?php
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="cointrace") {
		if (isset($URI[2])) {
			$type=$URI[2];
			if (isset($URI[3])) {
				$vinvout=$URI[3];
				if (isset($URI[4])) {
					$id=$URI[4];
				}
			}
		}
	}
}
function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}
?>
<style type="text/css">
	.node circle {
	  cursor: pointer;
	  fill: #fff;
	  stroke: purple;
	  stroke-width: 1.5px;
	}

	.node text {
	  font-size: 10px;
	}

	path.link {
	  fill: none;
	  stroke: #ccc;
	  stroke-width: 1.5px;
	}
</style>
<div class="container">
<?php 
if (isset($type) && isset($vinvout) && isset($id)) {
	
	//identify the biggest value
	if ($type=="received") {
		if ($vinvout=="vout") {
			$query = "SELECT address, value, parenttxid 
			FROM vout 
			WHERE id = '$id'";
		} 
		if ($vinvout=="vin") {
			$query = "SELECT vout.address, vout.value, vout.parenttxid
			FROM vin 
			INNER JOIN transactions AS tx
			ON tx.txid=vin.output_txid
			INNER JOIN vout
			ON vout.parenttxid=tx.id AND vout.n=vin.vout
			WHERE vin.id = '$id'";
		} 
		$values=array();
		//L1
		$result1 = $dbconn->query($query);
		while($row1 = $result1->fetch_assoc())
		{
			$L1_Address=$row1['address'];
			array_push($values,$row1['value']);
			if ($L1_Address==""){$L1_Address="Proof-of-Work";}
			$L1_Parenttxid=$row1['parenttxid'];
			//L2
			if ($L1_Address!="Proof-of-Work") {
				$query = "SELECT address, value, output_txid
				FROM vin 
				WHERE parenttxid = '$L1_Parenttxid'";
				$result2 = $dbconn->query($query);
				while($row2 = $result2->fetch_assoc())
				{
					$L2_Address=$row2['address'];
					array_push($values,$row2['value']);
					if ($L2_Address==""){$L2_Address="Proof-of-Work";}
					$L2_Output_txid=$row2['output_txid'];				
					//L3
					if ($L2_Address!="Proof-of-Work") {
						$query = "SELECT id
						FROM transactions
						WHERE txid = '$L2_Output_txid'";
						$result3 = $dbconn->query($query);
						while($row3 = $result3->fetch_assoc())
						{
							$L2_Parenttxid=$row3['id'];
						}
						
						$query = "SELECT address, value, output_txid
						FROM vin 
						WHERE parenttxid = '$L2_Parenttxid'";
						$result3 = $dbconn->query($query);
						while($row3 = $result3->fetch_assoc())
						{
							$L3_Address=$row3['address'];
							array_push($values,$row3['value']);
							if ($L3_Address==""){$L3_Address="Proof-of-Work";}
							$L3_Output_txid=$row3['output_txid'];					
							//L4
							if ($L3_Address!="Proof-of-Work") {
								$query = "SELECT id
								FROM transactions
								WHERE txid = '$L3_Output_txid'";
								$result4 = $dbconn->query($query);
								while($row4 = $result4->fetch_assoc())
								{
									$L3_Parenttxid=$row4['id'];
								}
							
								$query = "SELECT address, value, output_txid
								FROM vin 
								WHERE parenttxid = '$L3_Parenttxid'";
								$result4 = $dbconn->query($query);
								while($row4 = $result4->fetch_assoc())
								{
									$L4_Address=$row4['address'];
									array_push($values,$row4['value']);
									if ($L4_Address==""){$L4_Address="Proof-of-Work";}
									$L4_Output_txid=$row4['output_txid'];
								}
							}
						}
					}
				}
			}	
		}
	}
	rsort($values);
	$maxValue=$values[0];
	$data="";
	if ($type=="received") {
		if ($vinvout=="vout") {
			$query = "SELECT address, value, parenttxid
			FROM vout 
			WHERE id = '$id'";
		} 
		if ($vinvout=="vin") {
			$query = "SELECT vout.address, vout.value, vout.parenttxid
			FROM vin 
			INNER JOIN transactions AS tx
			ON tx.txid=vin.output_txid
			INNER JOIN vout
			ON vout.parenttxid=tx.id AND vout.n=vin.vout
			WHERE vin.id = '$id'";
		} 
		
		//L1
		$result1 = $dbconn->query($query);
		$count1=0;
		$count2=0;
		$count3=0;
		$count4=0;
		while($row1 = $result1->fetch_assoc())
		{
			$count1++;
			$L1_Address=$row1['address'];
			$value=round((($row1['value']*20)/$maxValue),0);
			$r=round((($row1['value']*20)/$maxValue),0);
			$value=$row1['value']." EMC";
			$L1_Value=$value;
			if ($r<1){$r=1;}
			if ($L1_Address==""){$L1_Address="Proof-of-Work";$value="";}
			$L1_Label="L1_".$row1['address'].$count1;
			$L1_Parenttxid=$row1['parenttxid'];
			$data=$data.'{ "name" : "'.$L1_Address.'", "r" : "'.$r.'", "value" : "'.$value.'", "label" : "'.$L1_Label.'", "parent":"null" },';

			//L2
			if ($L1_Address!="Proof-of-Work") {
				$query = "SELECT address, value, output_txid, coinbase
				FROM vin 
				WHERE parenttxid = '$L1_Parenttxid'";
				$result2 = $dbconn->query($query);
				while($row2 = $result2->fetch_assoc())
				{
					$count2++;
					$L2_Address=$row2['address'];
					$value=round((($row2['value']*20)/$maxValue),0);
					$r=round((($row2['value']*20)/$maxValue),0);
					$value=$row2['value']." EMC";
					if ($r<1){$r=1;}
					if ($L2_Address=="" && ($row2['coinbase']!="")){$L2_Address="Proof-of-Work";$value="";}
					if ($L2_Address=="" && ($row2['coinbase']=="")){$L2_Address="N/A";}
					$L2_Label="L2_".$row2['address'].$count2;
					$L2_Output_txid=$row2['output_txid'];
					$data=$data.'{ "name" : "'.$L2_Address.'", "r" : "'.$r.'", "value" : "'.$value.'", "label" : "'.$L2_Label.'", "parent":"'.$L1_Label.'" },';
					
					//L3
					if ($L2_Address!="Proof-of-Work") {
						$query = "SELECT id
						FROM transactions
						WHERE txid = '$L2_Output_txid'";
						$result3 = $dbconn->query($query);
						while($row3 = $result3->fetch_assoc())
						{
							$L2_Parenttxid=$row3['id'];
						}
						
						$query = "SELECT address, value, output_txid, coinbase
						FROM vin 
						WHERE parenttxid = '$L2_Parenttxid'";
						$result3 = $dbconn->query($query);
						while($row3 = $result3->fetch_assoc())
						{
							$count3++;
							$L3_Address=$row3['address'];
							$value=round((($row3['value']*20)/$maxValue),0);
							$r=round((($row3['value']*20)/$maxValue),0);
							$value=$row3['value']." EMC";
							if ($r<1){$r=1;}
							if ($L3_Address=="" && ($row3['coinbase']!="")){$L3_Address="Proof-of-Work";$value="";}
							if ($L3_Address=="" && ($row3['coinbase']=="")){$L3_Address="N/A";}
							$L3_Label="L3_".$row3['address'].$count3;
							$L3_Output_txid=$row3['output_txid'];
							$data=$data.'{ "name" : "'.$L3_Address.'", "r" : "'.$r.'", "value" : "'.$value.'", "label" : "'.$L3_Label.'", "parent":"'.$L2_Label.'" },';
							
							//L4
							if ($L3_Address!="Proof-of-Work") {
								$query = "SELECT id
								FROM transactions
								WHERE txid = '$L3_Output_txid'";
								$result4 = $dbconn->query($query);
								while($row4 = $result4->fetch_assoc())
								{
									$L3_Parenttxid=$row4['id'];
								}
							
								$query = "SELECT address, value, output_txid, coinbase
								FROM vin 
								WHERE parenttxid = '$L3_Parenttxid'";
								$result4 = $dbconn->query($query);
								while($row4 = $result4->fetch_assoc())
								{
									$count4++;
									$L4_Address=$row4['address'];
									$value=round((($row4['value']*20)/$maxValue),0);
									$r=round((($row4['value']*20)/$maxValue),0);
									$value=$row4['value']." EMC";
									if ($r<1){$r=1;}
									if ($L4_Address=="" && ($row4['coinbase']!="")){$L4_Address="Proof-of-Work";$value="";}
									if ($L4_Address=="" && ($row4['coinbase']=="")){$L4_Address="N/A";}
									$L4_Label="L4_".$row4['address'].$count3;
									$L4_Output_txid=$row4['output_txid'];
									$data=$data.'{ "name" : "'.$L4_Address.'", "r" : "'.$r.'", "value" : "'.$value.'", "label" : "'.$L4_Label.'", "parent":"'.$L3_Label.'" },';
								}
							}
						}
					}
				}
			}
		}
	}
}
if ($count1>=$count4 && $count1>=$count3 && $count1>=$count2) {
	$treeHeight=($count1*75);
}
if ($count2>=$count4 && $count2>=$count3 && $count2>=$count1) {
	$treeHeight=($count2*75);
}
if ($count3>=$count4 && $count3>=$count2 && $count3>=$count1) {
	$treeHeight=($count3*75);
}
if ($count4>=$count3 && $count4>=$count2 && $count4>=$count1) {
	$treeHeight=($count4*75);
}

echo '<h4>'.lang('TRACE_OF').' '.$L1_Address.' ('.$L1_Value.')</h4>';
?>

<div id="receivetrace"></div>

<script type="text/javascript">
var data = [
<?php echo $data; ?>
]; 

var dataMap = data.reduce(function(map, node) {
	map[node.label] = node;
	return map;
}, {});

var treeData = [];
data.forEach(function(node) {
 // add to parent
 var parent = dataMap[node.parent];
 if (parent) {
  // create child array if it doesn't exist
  (parent.children || (parent.children = []))
   // add node to child array
   .push(node);
 } else {
  // parent is null or missing
  treeData.push(node);
 }
});

var m = [10, 150, 10, 150],
    w = 1150 - m[1] - m[3],
    h = <?php echo $treeHeight; ?> - m[0] - m[2];

var cluster = d3.layout.cluster()   
.size([h, w]);

var diagonal = d3.svg.diagonal()   
.projection(function(d) { return [w-d.y, d.x]; });

var vis = d3.select("#receivetrace").append("svg:svg")    
.attr("width", w + m[1] + m[3])
.attr("height", h + m[0] + m[2]) 
.append("svg:g") 
.attr("transform", "translate(40, 0)");


var nodes = cluster.nodes(treeData[0]);
  
var link = vis.selectAll("path.link")      
.data(cluster.links(nodes))   
.enter().append("svg:path")     
.attr("class", "link")     
.attr("d", diagonal);

var node = vis.selectAll("g.node")  
.data(nodes)   
.enter().append("svg:g")    
.attr("class", "node")    
.attr("transform", function(d) { return "translate(" + (w-d.y) + "," + d.x + ")"; })
 
node.append("svg:circle")  
.attr("r", function(d) { return d.r; })

node.append("svg:text")      
.attr("dx", function(d) { return d.r; }) 
.attr("dy", -6)      
.text(function(d) { return d.name; });

node.append("svg:text")      
.attr("dx", function(d) { return d.r; })   
.attr("dy", 13)    
.text(function(d) { return d.value; });

    </script>
</div>

