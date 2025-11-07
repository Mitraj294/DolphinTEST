//
//  main.cpp
//  personalityProgram
//
//  Created by Andre Dyke on 6/10/24.
//
#include "digest.hpp"
#include "print.hpp"
#include "test.hpp"

// For Windows:
// std::string getExecutablePath(const char* argv[]) {
//     char result[PATH_MAX];
//     ssize_t count = readlink(argv[0], result, PATH_MAX);
//     std::string exePath;
//     if (count != -1) {
//         exePath = std::string(result, count);
//     } else {
//         exePath = std::string(argv[0]); // fallback if readlink fails
//     }
//     return exePath;
// }

int main(int argc, char **argv) {
    personality persona;
    word_info_struct word_info;
    std::string last_test = handle_argument(argc, argv);
    results results;

    if (argc < 2 || last_test == "help") {
        print_help();
        return 0;
    }

    std::string settingsName, selfTXT, concTXT, adjustTXT, 
                email, log_output;
    selfTXT = "test_input/settings/selfWeight.txt";
    concTXT = "test_input/settings/conceptWeight.txt";
    adjustTXT = "test_input/settings/adjustedWeight.txt";
    email = std::string(argv[1]);
    log_output = "test_output/log.txt";

    std::string cmd = "python3 code/sql_read.py '" + email + "'";
    int ret = system(cmd.c_str());

    if (ret != 0) {
        std::cerr << "Failed to run sql_read.py for email: " << email << std::endl;
    }

    std::ofstream logOut(log_output);
    if (!logOut.is_open()) {
        std::cerr << "Failed to open file: " << log_output << std::endl;
        return 1;
    }

    digestSelf(word_info, selfTXT, logOut);
    digestConc(word_info, concTXT, logOut);
    digestAdjust(word_info, adjustTXT, logOut);
    set_task_max(word_info.ceiling);

    
    persona = testAnalysis(word_info, email, logOut);
    process_results(persona, word_info, results);
    printResults(persona, word_info, results);
    cpp_to_py(results);
    return 0;
}
