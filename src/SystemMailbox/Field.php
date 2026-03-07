<?php

namespace App\Common\SystemMailbox;

use App\Common\Prototype\FieldPrototype;

class Field extends FieldPrototype {
	public static function systemMailbox(?array $a = NULL): array
	{
		if(is_array($a))
			extract($a);

		# If this is a new system mailbox
		if(!$system_mailbox_id){
			return \App\SubscriptionEmail\Field::subscriptionEmailProvider($a);
		}

		return \App\SubscriptionEmail\Field::smtp($a);
	}
}