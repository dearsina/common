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
	 * @param array|string $needs_joins
	 */
	protected function needsJoin($needs_joins): void
	{
		if(!$needs_joins){
			return;
		}

		# Must be an array form, but can also be delivered as a string
		if(!is_array($needs_joins)){
			$needs_joins = [$needs_joins];
		}

		# Handle missing joins
		if($missingJoins = array_values(array_diff($needs_joins, $this->joins ?: []))){
			//If there are any missing joins

			# Merge them with the existing joins
			$this->joins = array_merge($this->joins ?: [], $missingJoins);

			# Run the refresh to include the new joins
			$this->refresh();
			//FYI The refresh method isn't in the prototype
		}
	}
}