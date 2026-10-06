function login(){

        let username = document.getElementById("username").value;
        let password = document.getElementById("password").value;

    // first condition
    if(username == "admin" || password == "12345"){

        alert("Welcome Back My King!");
        return false; // para hindi sya pumasok or mag save ng data 


    }else{
    // condition
    alert("Username or Password is incorrect. Do you want to try again my King?");
    return false;

    

}
}