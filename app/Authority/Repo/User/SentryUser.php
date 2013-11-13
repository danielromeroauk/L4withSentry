<?php namespace Authority\Repo\User;

use Mail;
use Cartalyst\Sentry\Sentry;
use Authority\Repo\RepoAbstract;

class SentryUser extends RepoAbstract implements UserInterface {
	
	protected $sentry;

	/**
	 * Construct a new SentryUser Object
	 */
	public function __construct(Sentry $sentry)
	{
		$this->sentry = $sentry;

		// Get the Throttle Provider
		$this->throttleProvider = $this->sentry->getThrottleProvider();

		// Enable the Throttling Feature
		$this->throttleProvider->enable();
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store($data)
	{
		$result = array();
		try {
			//Attempt to register the user. 
			$user = $this->sentry->register(array('email' => e($data['email']), 'password' => e($data['password'])));

			//Get the activation code & prep data for email
			$data['activationCode'] = $user->GetActivationCode();
			$data['userId'] = $user->getId();

			//send email with link to activate.
			Mail::send('emails.auth.welcome', $data, function($m) use($data)
			{
			    $m->to(e($data['email']))->subject('Welcome to Laravel4 With Sentry');
			});

			//success!
	    	$result['success'] = true;
	    	$result['message'] = 'Your account has been created. Check your email for the confirmation link.';
		}
		catch (\Cartalyst\Sentry\Users\LoginRequiredException $e)
		{
		    $result['success'] = false;
	    	$result['message'] = 'Login field required.';
		}
		catch (\Cartalyst\Sentry\Users\UserExistsException $e)
		{
		    $result['success'] = false;
	    	$result['message'] = 'User already exists.';
		}

		return $result;
	}
	
	/**
	 * Update the specified resource in storage.
	 *
	 * @param  array $data
	 * @return Response
	 */
	public function update($data)
	{
		$result = array();
		try
		{
		    // Find the user using the user id
		    $user = $this->sentry->findUserById($data['id']);

		    // Update the user details
		    $user->first_name = $data['firstName'];
		    $user->last_name = $data['lastName'];

		    // Update group memberships
		    $allGroups = $this->sentry->getGroupProvider()->findAll();
		    foreach ($allGroups as $group)
		    {
		    	if (isset($data['groups'][$group->id])) 
                {
                    //The user should be added to this group
                    $user->addGroup($group);
                } else {
                    // The user should be removed from this group
                    $user->removeGroup($group);
                }
		    }

		    // Update the user
		    if ($user->save())
		    {
		        // User information was updated
		        $result['success'] = true;
	    		$result['message'] = 'Profile updated';
		    }
		    else
		    {
		        // User information was not updated
		        $result['success'] = false;
	    		$result['message'] = 'Unable to update profile';
		    }
		}
		catch (Cartalyst\Sentry\Users\UserExistsException $e)
		{
		    $result['success'] = false;
	    	$result['message'] = 'User already exists.';
		}
		catch (Cartalyst\Sentry\Users\UserNotFoundException $e)
		{
		    $result['success'] = false;
	    	$result['message'] = 'User not found';
		}

		return $result;
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		try
		{
		    // Find the user using the user id
		    $user = $this->sentry->findUserById($id);

		    // Delete the user
		    $user->delete();
		}
		catch (\Cartalyst\Sentry\Users\UserNotFoundException $e)
		{
		    return false;
		}
		return true;
	}

	/**
	 * Attempt activation for the specified user
	 * @param  int $id   
	 * @param  string $code 
	 * @return bool       
	 */
	public function activate($id, $code)
	{
		$result = array();
		try
		{
		    // Find the user using the user id
		    $user = $this->sentry->findUserById($id);

		    // Attempt to activate the user
		    if ($user->attemptActivation($code))
		    {
		        // User activation passed
		        $result['success'] = true;
	    		$result['message'] = 'Activation complete.';
		    }
		    else
		    {
		        // User activation failed
		        $result['success'] = false;
	    		$result['message'] = 'Activation could not be completed.';
		    }
		}
		catch (\Cartalyst\Sentry\Users\UserNotFoundException $e)
		{
		    $result['success'] = false;
	    	$result['message'] = 'User was not found.';
		}
		catch (\Cartalyst\Sentry\Users\UserAlreadyActivatedException $e)
		{
		    $result['success'] = false;
	    	$result['message'] = 'User is already activated.';
		}

		return $result;
	}

	/**
	 * Resend the activation email to the specified email address
	 * @param  Array $data
	 * @return Response
	 */
	public function resend($data)
	{
		$result = array();
		try {
            //Attempt to find the user. 
            $user = $this->sentry->getUserProvider()->findByLogin($data['email']);

            if (!$user->isActivated())
            {
                //Get the activation code & prep data for email
                $data['activationCode'] = $user->GetActivationCode();
                $data['userId'] = $user->getId();

                //send email with link to activate.
                Mail::send('emails.auth.welcome', $data, function($m) use ($data)
                {
                    $m->to($data['email'])->subject('Activate your account');
                });

                //success!
            	$result['success'] = true;
	    		$result['message'] = 'Check your email for the confirmation link.';
            }
            else 
            {
                $result['success'] = false;
	    		$result['message'] = 'That account has already been activated.';
            }

	    }
	    catch (\Cartalyst\Sentry\Users\LoginRequiredException $e)
	    {
	        $result['success'] = false;
	    	$result['message'] = 'Login field required.';
	    }
	    catch (\Cartalyst\Sentry\Users\UserExistsException $e)
	    {
	        $result['success'] = false;
	    	$result['message'] = 'User already exists.';
	    }
	    return $result;
	}

	/**
	 * Return a specific user from the given id
	 * 
	 * @param  integer $id
	 * @return User
	 */
	public function byId($id)
	{
		try
		{
		    $user = $this->sentry->findUserById($id);
		}
		catch (\Cartalyst\Sentry\Users\UserNotFoundException $e)
		{
		    return false;
		}
		return $user;
	}

	/**
	 * Return all the registered users
	 *
	 * @return stdObject Collection of users
	 */
	public function all()
	{
		$users = $this->sentry->findAllUsers();

		foreach ($users as $user) {
			if ($user->isActivated()) 
    		{
    			$user->status = "Active";
    		} 
    		else 
    		{
    			$user->status = "Not Active";
    		}

    		//Pull Suspension & Ban info for this user
    		$throttle = $this->throttleProvider->findByUserId($user->id);

    		//Check for suspension
    		if($throttle->isSuspended())
		    {
		        // User is Suspended
		        $user->status = "Suspended";
		    }

    		//Check for ban
		    if($throttle->isBanned())
		    {
		        // User is Banned
		        $user->status = "Banned";
		    }
		}

		return $users;
	}
}
