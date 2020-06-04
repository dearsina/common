<?php


namespace App\Common\CronJob;


use App\Common\str;

/**
 * Class Field
 * @package App\Common\CronJob
 */
class Field {


	public static function cronJob(?array $a = NULL) : array
	{
		if (is_array($a))
			extract($a);

		# The common path
		$common_path = __DIR__."/../../";

		# The core path
		$core_path = $_SERVER['DOCUMENT_ROOT']."/../app/";

		# Get classes from both
		$classes = array_merge(str::getClassesFromPath($common_path)?:[],str::getClassesFromPath($core_path)?:[]);

		# Sort them alphabetically
		sort($classes);

		# Load them in an options array
		foreach($classes as $m){
			$class_options[$m] = $m;
		}

		# If the class has already been identified, load up the methods
		if($class){
			foreach(str::getMethodsFromClass($class) as $m){
				$method_options[$m['name']] = $m['name'];
			}
		}

		# Create a parent child dependency
		$onChange = /** @lang JavaScript */<<<EOF
let class_name = $(this).val();

//If the user has chosen to erase the parent select
if(!class_name.length){
    // Remove all existing values
	$('#class-method option[value]').remove();
	
	// Add a relevant placeholder
	$('#class-method').setSelect2Placeholder("Select a class first.");
	return true;
}

// If the user has selected a parent, call for updated options for the child
ajaxCall("get_class_methods", "cron_job", false, {class_name: class_name}, function(data){
    //Remove the current values
    $('#class-method option[value]').remove();
    
    //If the parent has children
    if(typeof data.options === "object"){
        
        // For each child
		$.each(data.options, function(index, e){
		    
		    // Add them as an option to the child
			var newOption = new Option(e.text, e.id, false, false);
			$('#class-method').append(newOption).trigger('change');
			
		});        
    }    
    
    //If there is a new placeholder for the child
    if(typeof data.placeholder === "string"){
		$('#class-method').setSelect2Placeholder(data.placeholder);        
    }    
});
EOF;


		$title_fields = [
			[ //Row
				"name" => "title",
				"title" => "Cron job title",
				"required" => true,
				"value" => $title,
				"desc" => "Give the cron job a descriptive name.",
			],[
				"type" => "textarea",
				"name" => "desc",
				"title" => "Cron job description",
				"value" => $desc,
				"rows" => 7,
				"desc" => "Describe what the cron job does.",
				"required" => true
			]
		];

		$method_fields = [
			[
				"type" => "select",
				"required" => true,
				"options" => $class_options,
				"name" => "class",
				"value" => $class,
				"title" => "Class",
				"desc" => "In which class does your method reside?",
				"onChange" => $onChange
			],[
				"id" => "class-method",
				"type" => "select",
				"required" => true,
				"name" => "method",
				"options" => $method_options,
				"value" => $method,
				"title" => "Method",
				"desc" => "Which method do you want to run?",
			],[
				"type" => "select",
				"required" => true,
				"options" => [
					'@yearly' => 'Yearly',
					'@monthly' => 'Monthly',
					'@weekly' => 'Weekly',
					'@daily' => 'Daily',
					'@hourly' => 'Hourly',
					'* * * * *' => "Every minute"
				],
				"name" => "interval",
				"value" => $interval,
				"title" => "Interval",
				"desc" => "How often do you want the cron job to run?"
			],[
				"type" => "checkbox",
				"name" => "paused",
				"title" => "Paused",
				"desc" => "When a job is paused, it will not run as scheduled.",
				"value" => 1,
				"checked" => $paused === NULL ? true : $paused
			]
		];

		// Wrapper / Switch / Col (if switch is present, otherwise Row) / Row (if switch is present, otherwise Col)
		return [[
			$title_fields,
			$method_fields
		]];
	}
}