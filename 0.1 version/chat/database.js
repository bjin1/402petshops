const path = require("path");
const Sequelize = require("sequelize");

var sqlConfig = {
    host: "127.0.0.1",
    port: "3306",
    user: "root",
    password: "root",
    database: "petshop"
};

var sequelize = new Sequelize(sqlConfig.database, sqlConfig.user, sqlConfig.password, {
    host: sqlConfig.host,
    dialect: 'mysql',
    pool: {
        max: 10,
        min: 0,
        idle: 10000
    }
    , define: {
        timestamps: false
    }
});

// connect to the database
sequelize.authenticate().then(
    function () {
        console.log("Connection has been established successfully.");
    },
    function (err) {
        console.log("Unable to connect to the database:", err);
    }
);

// define message table
const Message = sequelize.define("message", {
    id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true,
        comment: "id"
    },
    sender: Sequelize.STRING,
    header: Sequelize.STRING,
    receiver: Sequelize.STRING,
    message: Sequelize.STRING,
    time: Sequelize.INTEGER,
});

//  SYNC SCHEMA
const initialiseDatabase = function (wipeAndClear, repopulate) {
    // sequelize.sync({force: wipeAndClear}).then(
    //     function () {
    //         console.log("Database Synchronised");
    //         if (repopulate) {
    //             repopulate();
    //         }
    //     },
    //     function (err) {
    //         console.log("An error occurred while creating the tables:", err);
    //     }
    // );
};

module.exports = {
    initialiseDatabase,
    Message,
    sequelize
};
