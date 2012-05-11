# PHP Pillow

#### Because Pillow lets your HEAD REST

Pillow is a php implementation of the fantastic python class slumber (https://github.com/dstufft/slumber)

##### Useage

Oauth2 Example - Calling standard Oauth2 interface
 
    $api = new Api\Pillow\Pillow('http://localhost/');
    $access_token = $api->v1OauthAccess_token()
                        ->post(array(
                                'client_id'          => '<client_code>',
                                'client_secret'      => '<client_secret>',
                                'scope'              => 'none',
                        ));

**Please Note** 

    $api->v1OauthAccess_token()->post(Array $array(), String $returnAs);

this is your url relative to the host-name provided when you instantiated the class.

This in turn, will then call

    http://localhost/v1/oauth/access_token/ 

Pillow converts to lowercase and **always** appends **/** to the url

    ->post(array('key'=>'value'))
    ->get(array('key'=>'value'))

1. ->put(array('key'=>'value')) **coming soon**
2. ->delete(array('key'=>'value')) **coming soon**




#### Questions, Complaints and Beer
sendrossemail+pillow[]gmail dot com
