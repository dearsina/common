<?php


namespace App\Common\Prototype;

/**
 * Class HandlerPrototype
 *
 * A prototype class for Handlers.
 *
 * @package App\Common\Prototype
 */
abstract class HandlerPrototype extends \App\Common\Prototype {
	public ?array $joins = [];

	/**
	 * If a method in the handler har join pre-requisites,
	 * add them to this method to ensure joins are included
	 * before the method is executed.
	 *
	 * @param array|string $requested_joins
	 */
	protected function setJoin($requested_joins): void
	{
		if(!$requested_joins){
			return;
		}

		# Must be an array form, but can also be delivered as a string
		if(!is_array($requested_joins)){
			$requested_joins = [$requested_joins];
		}

		# Handle missing joins
		if($missingJoins = array_values(array_diff($requested_joins, $this->joins ?: []))){
			//If there are any missing joins

			# Merge them with the existing joins
			$this->joins = array_merge($this->joins ?: [], $missingJoins);

			# Run the refresh to include the new joins
			$this->refresh();
			//FYI The refresh method isn't in the prototype
		}
	}
}