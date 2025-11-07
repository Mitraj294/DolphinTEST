#include <sstream>
#include <cstdlib>
#include <cmath>

class results{
public:
    std::string email;
    float self_a_score, self_b_score, self_c_score, self_d_score,
          dec_approach, self_avg, conc_avg,
          conc_a_score, conc_b_score, conc_c_score, conc_d_score;
          
    
    int self_total_words, conc_total_words, adj_total_words;
};

void printPercent(float num, std::ofstream& wf) {
    float percentage = num * 100;
    wf << std::fixed << std::setprecision(2) << percentage << "%\n";
}

void print_error() {
    std::cout << "ERROR: An error has occured, please check logs.";
}

void dupeWord(const std::string word, std::ofstream& logOut) {
    logOut << "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@";
    logOut << "\nCaution: word (" << word << ") appears in test file more than once";
    logOut << "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@\n\n";
}

void print_help() {
    std::cout << "##############################################################################################\n";
    std::cout << "### Please ensure the first argument is the name of the txt file you need to pass through. ###\n";
    std::cout << "### I.E. ./dolphin andreTest.txt                                                           ###\n";
    std::cout << "###                                                                                        ###\n";
    std::cout << "### Argument: '-r' enables retake mode.                                                    ###\n";
    std::cout << "### If retake has been enabled, the next argument should be the name of the file           ###\n";
    std::cout << "### that is associated with the retake. I.E. Andre_test.txt                                ###\n";
    std::cout << "### The retake file should be placed in the test_input folder.                             ###\n";
    std::cout << "##############################################################################################";
}

int cpp_to_py(results r) {
    // Build JSON
    std::ostringstream json;
    json << "{"
         << "\"email\":\"" << r.email << "\","
         << "\"self_total_words\":" << r.self_total_words << ","
         << "\"conc_total_words\":" << r.conc_total_words << ","
         << "\"adj_total_words\":" << r.adj_total_words << ","
         << "\"self_a\":" << r.self_a_score << ","
         << "\"self_b\":" << r.self_b_score << ","
         << "\"self_c\":" << r.self_c_score << ","
         << "\"self_d\":" << r.self_d_score << ","
         << "\"self_avg\":" << r.self_avg << ","
         << "\"dec_approach\":" << r.dec_approach << ","
         << "\"conc_a\":" << r.conc_a_score << ","
         << "\"conc_b\":" << r.conc_b_score << ","
         << "\"conc_c\":" << r.conc_c_score << ","
         << "\"conc_d\":" << r.conc_d_score << ","
         << "\"conc_avg\":" << r.conc_avg
         << "}";

    // Escape JSON string for command line
    std::string cmd = "python3 code/sql_insert.py '" + json.str() + "'";
    int ret = system(cmd.c_str());

    if (ret != 0) {
        std::cerr << "Failed to send JSON package" << std::endl;
    }

    return 0;
}


void printResults(personality& persona, word_info_struct& word_info, results r) {


    std::string outputFilePath = "test_output/" + persona.name + "_test.txt";
    std::ofstream testOut(outputFilePath);

        
    testOut << "-----------------Test Results for " << persona.name << "------------------\n";
    testOut << "\nTest Subject: " << persona.name << "\n\nSelf Total Words: " << r.self_total_words;
    testOut << "\nConcept Total Words: " << r.conc_total_words << "\nAdjusted Total Words: " << r.adj_total_words;
    testOut << "\n\n################################\n";
    testOut << "####### Weight Results #########\n";
    testOut << "################################\n";
    testOut << "\n******* Self Projection *******\n";
    testOut << "\nA percent: ";
    printPercent(r.self_a_score, testOut);
    testOut << "\nB percent: ";
    printPercent(r.self_b_score, testOut);
    testOut << "\nC percent: ";
    printPercent(r.self_c_score, testOut);
    testOut << "\nD percent: ";
    printPercent(r.self_d_score, testOut);
    testOut << "\nAverage: ";
    printPercent(r.self_avg, testOut);
    testOut << "\nDecision Approach: ";
    printPercent(r.dec_approach, testOut);
    testOut << '\n';

    std::string line = "----------------------------------------------------------------------------------------------------";
    std::string copy = line;
    copy[std::round(r.self_a_score*100)-1] = '0';
    testOut << "A | " << copy << '\n';
    copy = line;
    copy[std::round(r.self_b_score*100)-1] = '0';
    testOut << "B | " << copy << '\n';
    copy = line;
    copy[std::round(r.self_c_score*100)-1] = '0';
    testOut << "C | " << copy << '\n';
    copy = line;
    copy[std::round(r.self_d_score*100)-1] = '0';
    testOut << "D | " << copy << '\n';
    copy = line;
    copy[std::round(r.dec_approach*100)-1] = '0';
    testOut << "DA| " << copy << '\n';
    copy = line;
    copy[std::round(r.self_avg*100)-1] = '|';
    testOut << "L | " << copy << '\n';
    
    testOut << "\n******* Concept Projection ";
    testOut << "*******\n";
    testOut << "\nA percent: ";
    printPercent(r.conc_a_score, testOut);
    testOut << "\nB percent: ";
    printPercent(r.conc_b_score, testOut);
    testOut << "\nC percent: ";
    printPercent(r.conc_c_score, testOut);
    testOut << "\nD percent: ";
    printPercent(r.conc_d_score, testOut);
    testOut << "\nAverage: ";
    printPercent(r.conc_avg, testOut);
    testOut << '\n';
    line = "----------------------------------------------------------------------------------------------------";
    copy = line;
    copy[std::round(r.conc_a_score*100)-1] = '0';
    testOut << "A | " << copy << '\n';
    copy = line;
    copy[std::round(r.conc_b_score*100)-1] = '0';
    testOut << "B | " << copy << '\n';
    copy = line;
    copy[std::round(r.conc_c_score*100)-1] = '0';
    testOut << "C | " << copy << '\n';
    copy = line;
    copy[std::round(r.conc_d_score*100)-1] = '0';
    testOut << "D | " << copy << '\n';
    copy = line;
    copy[std::round(r.conc_avg*100)-1] = '|';
    testOut << "L | " << copy << '\n';
    
    testOut << "\n######################################\n";
    testOut << "### Count for Self word categories ###\n";
    testOut << "######################################\n";
    testOut << "A Count: " << persona.aSelfCount << '\n';
    testOut << "B Count: " << persona.bSelfCount << '\n';
    testOut << "C Count: " << persona.cSelfCount << '\n';
    testOut << "D Count: " << persona.dSelfCount << '\n';
    
    testOut << "\n---------- Self A words ----------";
    for (std::string i : persona.aSWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";

    testOut << "\n---------- Self B words ----------";
    for (std::string i : persona.bSWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";

    testOut << "\n---------- Self C words ----------";
    for (std::string i : persona.cSWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";
    
    testOut << "\n---------- Self D words ----------";
    for (std::string i : persona.dSWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";
    
    testOut << "\n#########################################\n";
    testOut << "### Count for Concept word categories ###\n";
    testOut << "#########################################\n";
    testOut << "A Count: " << persona.aConCount << '\n';
    testOut << "B Count: " << persona.bConCount << '\n';
    testOut << "C Count: " << persona.cConCount << '\n';
    testOut << "D Count: " << persona.dConCount << '\n';
    
    testOut << "\n---------- Concept A words ----------";
    for (std::string i : persona.aCWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";

    testOut << "\n---------- Concept B words ----------";
    for (std::string i : persona.bCWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";

    testOut << "\n---------- Concept C words ----------";
    for (std::string i : persona.cCWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";
    
    testOut << "\n---------- Concept D words ----------";
    for (std::string i : persona.dCWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";

    testOut << "\n----------------- End of " << persona.name << "'s test! ------------------\n\n\n\n";
}


void print_repeat(personality& persona, word_info_struct& word_info) {


    if(persona.name == "") {return;}
    std::string outputFilePath = "test_output/" + persona.name + "_repeat_test.txt";
    std::ofstream testOut(outputFilePath);

    // Self Weight:
    float aTot = (stof(persona.RI[0].substr(11, 5)) / 100);
    float bTot = (stof(persona.RI[1].substr(11, 5)) / 100);
    float cTot = (stof(persona.RI[2].substr(11, 5)) / 100);
    float dTot = (stof(persona.RI[3].substr(11, 5)) / 100);
    float dec_approach = stof(persona.RI[5].substr(19, 5)) / 100;
    
    // Adjusted Self:
    float aAdjTot = (float(persona.aAdj) / float(word_info.ceiling.a_adj_max));
    float bAdjTot = (float(persona.bAdj) / float(word_info.ceiling.b_adj_max));
    float cAdjTot = (float(persona.cAdj) / float(word_info.ceiling.c_adj_max));
    float dAdjTot = (float(persona.dAdj) / float(word_info.ceiling.d_adj_max));
    
    int self_total_count = persona.old_self_count;
    int con_total_count = persona.aConCount + persona.bConCount + persona.cConCount + persona.dConCount;
    int adj_total_count = persona.aAdjCount + persona.bAdjCount + persona.cAdjCount + persona.dAdjCount;

    testOut << "-----------------Test Results for " << persona.name << "------------------\n";
    testOut << "\nTest Subject: " << persona.name << "\n\nSelf Total Words: " << self_total_count;
    testOut << "\nConcept Total Words: " << con_total_count << "\nAdjusted Total Words: " << adj_total_count;
    testOut << "\n\n################################\n";
    testOut << "####### Weight Results #########\n";
    testOut << "################################\n";
    testOut << "\n******* Self Projection *******\n";
    testOut << "\nA percent: ";
    printPercent(aTot, testOut);
    testOut << "\nB percent: ";
    printPercent(bTot, testOut);
    testOut << "\nC percent: ";
    printPercent(cTot, testOut);
    testOut << "\nD percent: ";
    printPercent(dTot, testOut);
    float avg = (aTot + bTot + cTot + dTot) / 4;
    testOut << "\nAverage: ";
    printPercent(avg, testOut);
    testOut << "\nDecision Approach: ";
    printPercent(dec_approach, testOut);
    testOut << '\n';

    std::string line = "----------------------------------------------------------------------------------------------------";
    std::string copy = line;
    copy[std::round(aTot*100)-1] = '0';
    testOut << "A | " << copy << '\n';
    copy = line;
    copy[std::round(bTot*100)-1] = '0';
    testOut << "B | " << copy << '\n';
    copy = line;
    copy[std::round(cTot*100)-1] = '0';
    testOut << "C | " << copy << '\n';
    copy = line;
    copy[std::round(dTot*100)-1] = '0';
    testOut << "D | " << copy << '\n';
    copy = line;
    copy[std::round(dec_approach*100)-1] = '0';
    testOut << "DA| " << copy << '\n';
    copy = line;
    copy[std::round(avg*100)-1] = '|';
    testOut << "L | " << copy << '\n';
    
    // Concept Weight:
    float a_con_tot = (float(persona.aCon) / float(word_info.ceiling.a_con_max));
    float b_con_tot = (float(persona.bCon) / float(word_info.ceiling.b_con_max));
    float c_con_tot = (float(persona.cCon) / float(word_info.ceiling.c_con_max));
    float d_con_tot = (float(persona.dCon) / float(word_info.ceiling.d_con_max));
    testOut << "\n******* Concept Projection ";
    testOut << "*******\n";
    testOut << "\nA percent: ";
    printPercent(a_con_tot, testOut);
    testOut << "\nB percent: ";
    printPercent(b_con_tot, testOut);
    testOut << "\nC percent: ";
    printPercent(c_con_tot, testOut);
    testOut << "\nD percent: ";
    printPercent(d_con_tot, testOut);
    avg = (a_con_tot + b_con_tot + c_con_tot + d_con_tot) / 4;
    testOut << "\nAverage: ";
    printPercent(avg, testOut);
    testOut << '\n';
    line = "----------------------------------------------------------------------------------------------------";
    copy = line;
    copy[std::round(a_con_tot*100)-1] = '0';
    testOut << "A | " << copy << '\n';
    copy = line;
    copy[std::round(b_con_tot*100)-1] = '0';
    testOut << "B | " << copy << '\n';
    copy = line;
    copy[std::round(c_con_tot*100)-1] = '0';
    testOut << "C | " << copy << '\n';
    copy = line;
    copy[std::round(d_con_tot*100)-1] = '0';
    testOut << "D | " << copy << '\n';
    copy = line;
    copy[std::round(avg*100)-1] = '|';
    testOut << "L | " << copy << '\n';
    
    testOut << "\n######################################\n";
    testOut << "### Count for Self word categories ###\n";
    testOut << "######################################\n";
    testOut << "A Count: " << persona.aSelfCount << '\n';
    testOut << "B Count: " << persona.bSelfCount << '\n';
    testOut << "C Count: " << persona.cSelfCount << '\n';
    testOut << "D Count: " << persona.dSelfCount << '\n';
    
    testOut << "\n---------- Self A words ----------";
    for (std::string i : persona.aSWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";

    testOut << "\n---------- Self B words ----------";
    for (std::string i : persona.bSWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";

    testOut << "\n---------- Self C words ----------";
    for (std::string i : persona.cSWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";
    
    testOut << "\n---------- Self D words ----------";
    for (std::string i : persona.dSWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";
    
    testOut << "\n#########################################\n";
    testOut << "### Count for Concept word categories ###\n";
    testOut << "#########################################\n";
    testOut << "A Count: " << persona.aConCount << '\n';
    testOut << "B Count: " << persona.bConCount << '\n';
    testOut << "C Count: " << persona.cConCount << '\n';
    testOut << "D Count: " << persona.dConCount << '\n';
    
    testOut << "\n---------- Concept A words ----------";
    for (std::string i : persona.aCWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";

    testOut << "\n---------- Concept B words ----------";
    for (std::string i : persona.bCWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";

    testOut << "\n---------- Concept C words ----------";
    for (std::string i : persona.cCWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";
    
    testOut << "\n---------- Concept D words ----------";
    for (std::string i : persona.dCWords) {
        testOut << '\n' << i;
    }
    testOut << "\n---------------------------------\n";

    testOut << "\n----------------- End of " << persona.name << "'s test! ------------------\n\n\n\n";

}
