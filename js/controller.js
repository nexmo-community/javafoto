var phonecatApp = angular.module('fotoApp', []);

phonecatApp.controller('StripCtrl', function ($scope, $http) {
    $http.get('/data.json').success(function(data){
        $scope.sessions = [];
        angular.forEach(data.sessions, function(value){
           $scope.sessions.push(value);
        });

        console.log($scope.sessions);

    });
});